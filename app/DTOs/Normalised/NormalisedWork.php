<?php

declare(strict_types=1);

namespace App\DTOs\Normalised;

final readonly class NormalisedWork
{
    /**
     * @param  array<int, NormalisedAuthor>  $authors  Creators of the abstract work
     * @param  array<int, string>  $themaCodes  Pre-resolved Thema codes (rare — most providers don't ship Thema)
     * @param  array<int, string>  $lcshSubjects  Library of Congress headings (OpenLibrary uses these)
     */
    public function __construct(
        public string $originalTitle,
        public string $originalLanguage,
        public ?string $description = null,
        public ?int $firstPublicationYear = null,
        public ?string $wikidataId = null,
        public ?string $oclcWorkId = null,
        public array $authors = [],
        public array $themaCodes = [],
        public array $lcshSubjects = [],
        public ?string $sourceAuthorityId = null,
        public ?string $sourceSystem = null,
    ) {}
}
