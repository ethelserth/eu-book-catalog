<?php

declare(strict_types=1);

namespace App\Clients\Contracts;

use DateTimeInterface;
use Generator;

interface BiblionetClientInterface
{
    /**
     * Authenticate and cache the token.
     * Called automatically before the first request; rarely needed directly.
     *
     * @throws \App\Clients\Exceptions\BiblionetAuthException
     */
    public function authenticate(): void;

    /**
     * Fetch a single page of books.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \App\Clients\Exceptions\BiblionetAuthException
     * @throws \App\Clients\Exceptions\BiblionetRateLimitException
     * @throws \App\Clients\Exceptions\BiblionetApiException
     */
    public function fetchBooks(int $page = 1, int $perPage = 100): array;

    /**
     * Lazily yield all books modified since the given date.
     * Uses a Generator so memory usage stays flat regardless of result size.
     *
     * @return Generator<int, array<string, mixed>>
     *
     * @throws \App\Clients\Exceptions\BiblionetAuthException
     * @throws \App\Clients\Exceptions\BiblionetRateLimitException
     * @throws \App\Clients\Exceptions\BiblionetApiException
     */
    public function fetchBooksSince(DateTimeInterface $since): Generator;

    /**
     * Fetch a single book by its BIBLIONET ID.
     *
     * @return array<string, mixed>
     *
     * @throws \App\Clients\Exceptions\BiblionetAuthException
     * @throws \App\Clients\Exceptions\BiblionetApiException
     */
    public function fetchBook(string $id): array;
}
