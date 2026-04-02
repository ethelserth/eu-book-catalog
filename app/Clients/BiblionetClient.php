<?php

declare(strict_types=1);

namespace App\Clients;

use App\Clients\Contracts\BiblionetClientInterface;
use App\Clients\Exceptions\BiblionetApiException;
use App\Clients\Exceptions\BiblionetAuthException;
use App\Clients\Exceptions\BiblionetRateLimitException;
use DateTimeInterface;
use Generator;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BiblionetClient implements BiblionetClientInterface
{
    /**
     * Cache key for the OAuth access token.
     * Prefixed so it's easy to flush: Cache::forget('biblionet.token')
     */
    private const TOKEN_CACHE_KEY = 'biblionet.token';

    /**
     * How many seconds before token expiry we consider it stale.
     * This guards against using a token that expires mid-request.
     */
    private const TOKEN_EXPIRY_BUFFER_SECONDS = 60;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly int $rateLimit, // requests per second
    ) {}

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    /**
     * Obtain a Bearer token from BIBLIONET and cache it.
     *
     * We store the token in the Laravel cache (Redis in production,
     * database in local dev) so that multiple queue workers share one token
     * and we don't hammer the auth endpoint.
     */
    public function authenticate(): void
    {
        // If already cached, nothing to do.
        if (Cache::has(self::TOKEN_CACHE_KEY)) {
            return;
        }

        $response = Http::asForm()->post("{$this->baseUrl}/token", [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
        ]);

        if ($response->status() === 401 || $response->status() === 403) {
            throw new BiblionetAuthException(
                'BIBLIONET authentication failed. Check BIBLIONET_CLIENT_ID and BIBLIONET_CLIENT_SECRET in .env'
            );
        }

        if (! $response->successful()) {
            throw new BiblionetApiException(
                "Authentication request failed with HTTP {$response->status()}",
                $response->status()
            );
        }

        $data      = $response->json();
        $token     = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        if (! $token) {
            throw new BiblionetAuthException('BIBLIONET returned no access_token in auth response.');
        }

        // Cache slightly shorter than the real expiry to avoid edge cases.
        $ttl = max(0, $expiresIn - self::TOKEN_EXPIRY_BUFFER_SECONDS);

        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        Log::info('BiblionetClient: authenticated, token valid for ' . $ttl . 's');
    }

    // -------------------------------------------------------------------------
    // Public API methods
    // -------------------------------------------------------------------------

    /**
     * Fetch one page of books.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchBooks(int $page = 1, int $perPage = 100): array
    {
        $response = $this->get('/books', [
            'page'     => $page,
            'per_page' => $perPage,
        ]);

        // The API may return { "data": [...] } or a bare array.
        return $response['data'] ?? $response;
    }

    /**
     * Lazily yield all books modified since the given date.
     *
     * Using a Generator (yield) means we never hold the entire dataset in
     * memory. The caller iterates with foreach and each iteration triggers
     * the next API call only when needed.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function fetchBooksSince(DateTimeInterface $since): Generator
    {
        $page  = 1;
        $since = $since->format('Y-m-d');

        do {
            $this->throttle();

            $response = $this->get('/books', [
                'modified_since' => $since,
                'page'           => $page,
                'per_page'       => 100,
            ]);

            $books   = $response['data']    ?? $response;
            $hasMore = $response['has_more'] ?? (count($books) === 100);

            foreach ($books as $book) {
                yield $book;
            }

            $page++;

        } while ($hasMore && ! empty($books));
    }

    /**
     * Fetch a single book by ID.
     *
     * @return array<string, mixed>
     */
    public function fetchBook(string $id): array
    {
        return $this->get("/books/{$id}");
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Make an authenticated GET request with automatic token refresh.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $params = []): array
    {
        $this->ensureAuthenticated();

        $token = Cache::get(self::TOKEN_CACHE_KEY);

        try {
            $response = Http::withToken($token)
                ->retry(3, 500, function (\Throwable $e) {
                    // Only retry on connection errors and 5xx, not on 4xx.
                    // The second argument to retry() is the delay in ms.
                    return $e instanceof RequestException
                        && $e->response->serverError();
                })
                ->get("{$this->baseUrl}{$endpoint}", $params);

        } catch (RequestException $e) {
            throw new BiblionetApiException(
                "BIBLIONET request failed: {$e->getMessage()}",
                $e->response->status(),
                $e
            );
        }

        return $this->parseResponse($response, $endpoint);
    }

    /**
     * Parse a response, throwing typed exceptions for known error conditions.
     *
     * @return array<string, mixed>
     */
    private function parseResponse(
        \Illuminate\Http\Client\Response $response,
        string $endpoint,
    ): array {
        if ($response->status() === 401 || $response->status() === 403) {
            // Token may have expired despite our buffer; clear it so the next
            // call re-authenticates.
            Cache::forget(self::TOKEN_CACHE_KEY);
            throw new BiblionetAuthException(
                "BIBLIONET returned {$response->status()} for {$endpoint}. Token cleared."
            );
        }

        if ($response->status() === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?: 60);
            throw new BiblionetRateLimitException($retryAfter);
        }

        if (! $response->successful()) {
            throw new BiblionetApiException(
                "BIBLIONET API error {$response->status()} for {$endpoint}",
                $response->status()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Ensure we have a valid token, authenticating if necessary.
     */
    private function ensureAuthenticated(): void
    {
        if (! Cache::has(self::TOKEN_CACHE_KEY)) {
            $this->authenticate();
        }
    }

    /**
     * Enforce the configured rate limit by sleeping between requests.
     *
     * usleep() takes microseconds (1 second = 1,000,000 µs).
     * At rateLimit=1 we sleep 1 second between calls.
     */
    private function throttle(): void
    {
        if ($this->rateLimit > 0) {
            usleep((int) (1_000_000 / $this->rateLimit));
        }
    }
}
