<?php

declare(strict_types=1);

namespace App\Clients\Contracts;

use DateTimeInterface;

interface OpenLibraryClientInterface
{
    /**
     * Fetch an edition record by ISBN (10 or 13).
     * Returns null if OpenLibrary has no record for this ISBN.
     *
     * @return array<string, mixed>|null
     */
    public function fetchByIsbn(string $isbn): ?array;

    /**
     * Fetch an Edition record directly by OpenLibrary book ID (e.g. "OL7353617M").
     * This hits /books/{olid}.json — used when processing RecentChanges /books/ keys.
     *
     * @return array<string, mixed>
     */
    public function fetchEdition(string $olid): array;

    /**
     * Fetch a Work record by OpenLibrary ID (e.g. "OL45804W").
     *
     * @return array<string, mixed>
     */
    public function fetchWork(string $olid): array;

    /**
     * Fetch all editions belonging to a Work.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchWorkEditions(string $olid, int $limit = 50): array;

    /**
     * Fetch an Author record by OpenLibrary ID (e.g. "OL23919A").
     * Author records include remote_ids: viaf, isni, wikidata — free authority data.
     *
     * @return array<string, mixed>
     */
    public function fetchAuthor(string $olid): array;

    /**
     * Search the catalog. Returns an array of search result documents.
     *
     * @return array{numFound: int, docs: array<int, array<string, mixed>>}
     */
    public function search(string $query, int $limit = 20, int $offset = 0): array;

    /**
     * Fetch change records for a given day.
     * Used for incremental sync: pass yesterday's date to get all updates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchChanges(DateTimeInterface $date, int $limit = 100, int $offset = 0): array;
}
