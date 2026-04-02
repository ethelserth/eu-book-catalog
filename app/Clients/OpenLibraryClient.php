<?php

declare(strict_types=1);

namespace App\Clients;

use App\Clients\Contracts\OpenLibraryClientInterface;
use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenLibraryClient implements OpenLibraryClientInterface
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $userAgent,
        private readonly int $rateLimit,
    ) {}

    // -------------------------------------------------------------------------
    // Interface implementation
    // -------------------------------------------------------------------------

    public function fetchByIsbn(string $isbn): ?array
    {
        // OL returns 301 → /books/OL123M.json, then 200 with the edition record.
        // Laravel's HTTP client follows redirects automatically.
        // A 404 means OL has no record for this ISBN — return null, not an error.
        $response = Http::withUserAgent($this->userAgent)
            ->get("{$this->baseUrl}/isbn/{$isbn}.json");

        if ($response->notFound()) {
            return null;
        }

        $this->guardResponse($response, "/isbn/{$isbn}.json");

        return $response->json();
    }

    public function fetchEdition(string $olid): array
    {
        $olid = $this->normaliseOlid($olid);

        return $this->get("/books/{$olid}.json");
    }

    public function fetchWork(string $olid): array
    {
        $olid = $this->normaliseOlid($olid);

        return $this->get("/works/{$olid}.json");
    }

    public function fetchWorkEditions(string $olid, int $limit = 50): array
    {
        $olid = $this->normaliseOlid($olid);
        $data = $this->get("/works/{$olid}/editions.json", ['limit' => $limit]);

        return $data['entries'] ?? [];
    }

    public function fetchAuthor(string $olid): array
    {
        $olid = $this->normaliseOlid($olid);

        return $this->get("/authors/{$olid}.json");
    }

    public function search(string $query, int $limit = 20, int $offset = 0): array
    {
        $this->throttle();

        return $this->get('/search.json', [
            'q' => $query,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function fetchChanges(DateTimeInterface $date, int $limit = 100, int $offset = 0): array
    {
        $path = '/recentchanges/'.$date->format('Y/m/d').'.json';

        return $this->get($path, [
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Perform a GET request with User-Agent, throttling, and error handling.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function get(string $path, array $params = []): array
    {
        $this->throttle();

        $response = Http::withUserAgent($this->userAgent)
            ->timeout(30)
            ->retry(3, 1000, function (\Throwable $e) {
                // Retry on server errors and connection timeouts — not on 4xx.
                return $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError());
            })
            ->get("{$this->baseUrl}{$path}", $params);

        $this->guardResponse($response, $path);

        return $response->json() ?? [];
    }

    /**
     * Throw a descriptive exception for non-successful responses.
     * 404s on /isbn/ are handled by the caller; all other 4xx/5xx are errors.
     */
    private function guardResponse(
        Response $response,
        string $path
    ): void {
        if (! $response->successful()) {
            Log::warning('OpenLibraryClient: non-200 response', [
                'path' => $path,
                'status' => $response->status(),
            ]);

            throw new \RuntimeException(
                "OpenLibrary returned HTTP {$response->status()} for {$path}"
            );
        }
    }

    /**
     * Strip the /works/ or /authors/ prefix if a full path is passed.
     * Normalises "OL45804W", "/works/OL45804W", "/works/OL45804W.json"
     * all to just "OL45804W".
     */
    private function normaliseOlid(string $olid): string
    {
        // Remove leading path segments and .json suffix.
        return preg_replace('#^.*?([A-Z0-9]+[A-Z])(?:\.json)?$#', '$1', $olid);
    }

    /**
     * Honour the configured rate limit.
     */
    private function throttle(): void
    {
        if ($this->rateLimit > 0) {
            usleep((int) (1_000_000 / $this->rateLimit));
        }
    }
}
