<?php

declare(strict_types=1);

namespace App\DTOs\Normalised;

/**
 * Provider-agnostic shape of a single contributor (author, translator, editor…).
 *
 * Mappers populate whichever authority IDs the source provides. The CatalogWriter
 * uses these (plus the display name) to deduplicate against the authors table.
 *
 * Phase 6 (authority matching) will use VIAF / Wikidata APIs to *enrich* these
 * — for now we only pass through what the source itself supplies.
 */
final readonly class NormalisedAuthor
{
    /**
     * @param  string  $role  'author' | 'co_author' | 'editor' | 'compiler'  (work_authors.role enum)
     *                        or 'translator' | 'illustrator' | 'narrator' | 'introduction' (expression_contributors.role)
     * @param  array<string>  $alternateNames  Other forms of the name encountered in the source
     */
    public function __construct(
        public string $displayName,
        public ?string $sortName = null,
        public ?int $birthYear = null,
        public ?int $deathYear = null,
        public ?string $nationality = null,
        public ?string $viafId = null,
        public ?string $isni = null,
        public ?string $wikidataId = null,
        public string $role = 'author',
        public int $position = 0,
        public array $alternateNames = [],
        /** Provider-native identifier (e.g. OpenLibrary OLID or BIBLIONET WriterID). */
        public ?string $sourceAuthorityId = null,
        public ?string $sourceSystem = null,
    ) {}
}
