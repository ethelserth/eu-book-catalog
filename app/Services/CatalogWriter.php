<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Normalised\NormalisedAuthor;
use App\DTOs\Normalised\NormalisedPublisher;
use App\DTOs\Normalised\NormalisedRecord;
use App\DTOs\Normalised\NormalisedWork;
use App\Models\Author;
use App\Models\AuthorNameVariant;
use App\Models\Edition;
use App\Models\EditionProvenanceLog;
use App\Models\Expression;
use App\Models\Provenance;
use App\Models\Publisher;
use App\Models\PublisherNameVariant;
use App\Models\RawIngestionRecord;
use App\Models\Work;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Provider-agnostic upsert into the FRBR catalog.
 *
 * Public entry point: write($record, $provenance, $rawRecord)
 *
 *   - $record is the NormalisedRecord produced by a mapper.
 *   - $provenance is the batch we're processing under (created by the runner).
 *   - $rawRecord is the source row from raw_ingestion_records — used to attach
 *     the resulting edition_id back onto the raw row.
 *
 * Returns the resulting Edition (if a full-chain record), Work (work-only),
 * Author (author-only), or null when nothing was written.
 *
 * **Deduplication strategy (Phase 5, naive — Phase 6 will refine):**
 *   Authors:    viaf_id → wikidata_id → isni → name_variant.name → display_name
 *   Publishers: name match (case-insensitive) → name_variant.name
 *   Works:      (lead author + normalised original_title)  with originallanguage filter
 *   Expression: (work_id, language, expression_type)
 *   Editions:   isbn13  →  composite (publisher_id, expression_id, year, format)
 *
 * Everything happens inside a transaction so a partial failure rolls back.
 */
final class CatalogWriter
{
    public function write(
        NormalisedRecord $record,
        Provenance $provenance,
        RawIngestionRecord $rawRecord,
    ): Edition|Work|Author|null {
        return DB::transaction(function () use ($record, $provenance, $rawRecord) {
            if ($record->isAuthorOnly()) {
                return $this->upsertAuthor($record->author);
            }

            if ($record->work === null) {
                return null;
            }

            $work = $this->upsertWork($record->work);

            if ($record->expression === null || $record->edition === null) {
                return $work;
            }

            $expression = $this->upsertExpression($work, $record);
            $publisher = $record->edition->publisher !== null
                ? $this->upsertPublisher($record->edition->publisher)
                : $this->fallbackPublisher();

            [$edition, $action] = $this->upsertEdition($expression, $publisher, $record);

            EditionProvenanceLog::create([
                'edition_id' => $edition->id,
                'provenance_id' => $provenance->id,
                'action' => $action,
                'previous_data' => null,
            ]);

            $rawRecord->markCompleted($edition);

            return $edition;
        });
    }

    // -------------------------------------------------------------------------
    // Authors
    // -------------------------------------------------------------------------

    private function upsertAuthor(NormalisedAuthor $a): Author
    {
        $author = $this->findExistingAuthor($a);

        if ($author === null) {
            $author = Author::create([
                'display_name' => $a->displayName,
                'sort_name' => $a->sortName ?? $this->deriveSortName($a->displayName),
                'birth_year' => $a->birthYear,
                'death_year' => $a->deathYear,
                'nationality' => $a->nationality,
                'viaf_id' => $a->viafId,
                'isni' => $a->isni,
                'wikidata_id' => $a->wikidataId,
                'authority_confidence' => $a->viafId || $a->wikidataId || $a->isni ? 0.90 : 0.30,
                'needs_review' => ! ($a->viafId || $a->wikidataId || $a->isni),
            ]);
        } else {
            // Backfill missing authority IDs when a later source supplies them
            $update = array_filter([
                'viaf_id' => $author->viaf_id ?? $a->viafId,
                'isni' => $author->isni ?? $a->isni,
                'wikidata_id' => $author->wikidata_id ?? $a->wikidataId,
                'birth_year' => $author->birth_year ?? $a->birthYear,
                'death_year' => $author->death_year ?? $a->deathYear,
            ], fn ($v) => $v !== null);

            if ($update !== []) {
                $author->fill($update)->save();
            }
        }

        $this->recordAuthorNameVariant($author, $a->displayName, $a->sourceSystem);
        foreach ($a->alternateNames as $alt) {
            $this->recordAuthorNameVariant($author, $alt, $a->sourceSystem);
        }

        return $author;
    }

    private function findExistingAuthor(NormalisedAuthor $a): ?Author
    {
        // 1) Authority IDs are the strongest signal
        if ($a->viafId) {
            $hit = Author::where('viaf_id', $a->viafId)->first();
            if ($hit !== null) {
                return $hit;
            }
        }
        if ($a->wikidataId) {
            $hit = Author::where('wikidata_id', $a->wikidataId)->first();
            if ($hit !== null) {
                return $hit;
            }
        }
        if ($a->isni) {
            $hit = Author::where('isni', $a->isni)->first();
            if ($hit !== null) {
                return $hit;
            }
        }

        // 2) Look up via name variant index (catches "Νίκος Καζαντζάκης" ≡ "Nikos Kazantzakis")
        $variant = AuthorNameVariant::where('name', $a->displayName)->first();
        if ($variant !== null) {
            return $variant->author;
        }

        // 3) Final fallback: exact display_name match
        return Author::where('display_name', $a->displayName)->first();
    }

    private function recordAuthorNameVariant(Author $author, string $name, ?string $source): void
    {
        $name = trim($name);
        if ($name === '') {
            return;
        }

        AuthorNameVariant::updateOrCreate(
            ['author_id' => $author->id, 'name' => $name],
            [
                'script' => $this->detectScript($name),
                'source' => $source ?? 'manual',
            ],
        );
    }

    private function detectScript(string $name): string
    {
        if (preg_match('/\p{Greek}/u', $name)) {
            return 'greek';
        }
        if (preg_match('/\p{Cyrillic}/u', $name)) {
            return 'cyrillic';
        }
        if (preg_match('/\p{Latin}/u', $name)) {
            return 'latin';
        }

        return 'other';
    }

    /** Generate "Last, First" sort form. Handles Greek scripts the same as Latin. */
    private function deriveSortName(string $displayName): string
    {
        $parts = preg_split('/\s+/', trim($displayName)) ?: [];

        if (count($parts) < 2) {
            return $displayName;
        }

        $last = array_pop($parts);
        $rest = implode(' ', $parts);

        return "{$last}, {$rest}";
    }

    // -------------------------------------------------------------------------
    // Publishers
    // -------------------------------------------------------------------------

    private function upsertPublisher(NormalisedPublisher $p): Publisher
    {
        $publisher = Publisher::whereRaw('LOWER(name) = ?', [mb_strtolower($p->name)])->first();

        if ($publisher === null) {
            $variantHit = PublisherNameVariant::where('name', $p->name)->first();
            $publisher = $variantHit?->publisher;
        }

        if ($publisher === null) {
            $publisher = Publisher::create([
                'name' => $p->name,
                'country' => $p->country,
                'isni' => $p->isni,
                'website' => $p->website,
            ]);
        }

        PublisherNameVariant::updateOrCreate(
            ['publisher_id' => $publisher->id, 'name' => $p->name],
            ['source' => $p->sourceSystem ?? 'manual'],
        );

        return $publisher;
    }

    /**
     * Editions can't be FK-null on publisher_id, so we need a deterministic
     * "Unknown Publisher" sentinel when a payload omits the publisher.
     */
    private function fallbackPublisher(): Publisher
    {
        return Publisher::firstOrCreate(
            ['name' => 'Unknown Publisher'],
            ['country' => null],
        );
    }

    // -------------------------------------------------------------------------
    // Works
    // -------------------------------------------------------------------------

    private function upsertWork(NormalisedWork $w): Work
    {
        // Resolve author rows first — they're the strongest dedup signal for the work
        $authors = array_map(fn (NormalisedAuthor $a) => $this->upsertAuthor($a), $w->authors);
        $leadAuthor = $authors[0] ?? null;

        // 1) Wikidata Q-id is a definitive identifier
        if ($w->wikidataId) {
            $hit = Work::where('wikidata_id', $w->wikidataId)->first();
            if ($hit !== null) {
                $this->attachWorkAuthors($hit, $w->authors, $authors);

                return $hit;
            }
        }

        // 2) Lead author + normalised original_title + language
        $normalisedTitle = $this->normaliseTitle($w->originalTitle);

        if ($leadAuthor !== null) {
            $hit = Work::query()
                ->whereRaw('LOWER(original_title) = ?', [$normalisedTitle])
                ->where('original_language', $w->originalLanguage)
                ->whereHas('authors', fn ($q) => $q->where('authors.id', $leadAuthor->id))
                ->first();

            if ($hit !== null) {
                $this->attachWorkAuthors($hit, $w->authors, $authors);

                return $hit;
            }
        }

        $work = Work::create([
            'original_title' => $w->originalTitle,
            'original_language' => $w->originalLanguage,
            'description' => $w->description,
            'first_publication_year' => $w->firstPublicationYear,
            'wikidata_id' => $w->wikidataId,
            'oclc_work_id' => $w->oclcWorkId,
        ]);

        $this->attachWorkAuthors($work, $w->authors, $authors);

        return $work;
    }

    /**
     * @param  array<int, NormalisedAuthor>  $normalisedAuthors
     * @param  array<int, Author>  $authors
     */
    private function attachWorkAuthors(Work $work, array $normalisedAuthors, array $authors): void
    {
        foreach ($authors as $i => $author) {
            $work->authors()->syncWithoutDetaching([
                $author->id => [
                    'role' => $normalisedAuthors[$i]->role ?: 'author',
                    'position' => $normalisedAuthors[$i]->position ?: $i,
                ],
            ]);
        }
    }

    private function normaliseTitle(string $title): string
    {
        $title = mb_strtolower(trim($title));
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return $title;
    }

    // -------------------------------------------------------------------------
    // Expressions
    // -------------------------------------------------------------------------

    private function upsertExpression(Work $work, NormalisedRecord $r): Expression
    {
        $expr = $r->expression;
        if ($expr === null) {
            throw new RuntimeException('upsertExpression called without an expression DTO.');
        }

        // If the work's original_language equals this expression's language,
        // it's not actually a translation — override the mapper's guess.
        $type = $expr->expressionType;
        if ($work->original_language === $expr->language && $type === 'translation') {
            $type = 'original';
        }

        return Expression::firstOrCreate(
            [
                'work_id' => $work->id,
                'language' => $expr->language,
                'expression_type' => $type,
            ],
            [
                'title' => $expr->title,
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Editions
    // -------------------------------------------------------------------------

    /** @return array{0: Edition, 1: 'created'|'updated'} */
    private function upsertEdition(
        Expression $expression,
        Publisher $publisher,
        NormalisedRecord $r,
    ): array {
        $e = $r->edition;
        if ($e === null) {
            throw new RuntimeException('upsertEdition called without an edition DTO.');
        }

        $attributes = [
            'expression_id' => $expression->id,
            'publisher_id' => $publisher->id,
            'isbn13' => $e->isbn13,
            'isbn10' => $e->isbn10,
            'publication_date' => $e->publicationDate,
            'publication_year' => $e->publicationYear,
            'format' => $e->format,
            'pages' => $e->pages,
            'cover_url' => $e->coverUrl,
            'source_system' => $e->sourceSystem ?? 'unknown',
            'source_record_id' => $e->sourceAuthorityId ?? '',
        ];

        // 1) ISBN-13 is the cleanest identity
        if ($e->isbn13) {
            $existing = Edition::where('isbn13', $e->isbn13)->first();
            if ($existing !== null) {
                $existing->fill($attributes)->save();

                return [$existing, 'updated'];
            }
        }

        // 2) Composite uniqueness for ISBN-less editions
        $composite = Edition::query()
            ->where('publisher_id', $publisher->id)
            ->where('expression_id', $expression->id)
            ->where('publication_year', $e->publicationYear)
            ->where('format', $e->format)
            ->first();

        if ($composite !== null) {
            $composite->fill($attributes)->save();

            return [$composite, 'updated'];
        }

        return [Edition::create($attributes), 'created'];
    }
}
