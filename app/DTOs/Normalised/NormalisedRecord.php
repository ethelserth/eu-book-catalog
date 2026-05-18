<?php

declare(strict_types=1);

namespace App\DTOs\Normalised;

/**
 * Umbrella DTO returned by every mapper.
 *
 * Different record_types fill in different fields:
 *   - record_type=author  → only $author is set
 *   - record_type=work    → $work (+ its authors[]) is set
 *   - record_type=edition → $edition + $expression + $work all set (full FRBR chain)
 *   - record_type=title   → BIBLIONET "title" = same as 'edition' (full chain)
 *
 * CatalogWriter inspects which fields are populated to decide what to upsert.
 *
 * Why one DTO instead of separate types? The pipeline runner doesn't have to
 * branch on record_type — it just calls write($record) and the writer figures
 * out what's there. Simpler signature, less router code.
 */
final readonly class NormalisedRecord
{
    public function __construct(
        public ?NormalisedWork $work = null,
        public ?NormalisedExpression $expression = null,
        public ?NormalisedEdition $edition = null,
        public ?NormalisedAuthor $author = null,
    ) {}

    public function isAuthorOnly(): bool
    {
        return $this->author !== null && $this->work === null && $this->edition === null;
    }

    public function isWorkOnly(): bool
    {
        return $this->work !== null && $this->edition === null;
    }

    public function isFullChain(): bool
    {
        return $this->edition !== null && $this->expression !== null && $this->work !== null;
    }
}
