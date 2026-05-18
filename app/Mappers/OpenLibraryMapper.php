<?php

declare(strict_types=1);

namespace App\Mappers;

use App\DTOs\Normalised\NormalisedAuthor;
use App\DTOs\Normalised\NormalisedEdition;
use App\DTOs\Normalised\NormalisedExpression;
use App\DTOs\Normalised\NormalisedPublisher;
use App\DTOs\Normalised\NormalisedRecord;
use App\DTOs\Normalised\NormalisedWork;
use App\Models\RawIngestionRecord;
use App\Support\IsoLanguage;
use App\Support\PublicationDateParser;

/**
 * Maps an OpenLibrary raw payload into the shared NormalisedRecord shape.
 *
 * Three record_types are supported:
 *
 *   record_type = 'author'   → only the author (no work, no edition)
 *   record_type = 'work'     → work + its author refs (resolved via OLID lookups)
 *   record_type = 'edition'  → full FRBR chain (work + expression + edition)
 *
 * OLID-based author/work cross-references on edition records are resolved by
 * looking up previously-staged raw_ingestion_records of the matching type.
 * If we don't yet have that companion record staged, we fall back to a stub
 * (by_statement text on editions, no work title on bare work refs) and let
 * the review queue surface gaps for human cleanup.
 */
final class OpenLibraryMapper implements MapperInterface
{
    public function sourceSystem(): string
    {
        return 'openlibrary';
    }

    public function map(RawIngestionRecord $record): ?NormalisedRecord
    {
        $payload = $record->payload;

        if (! is_array($payload)) {
            return null;
        }

        return match ($record->record_type) {
            'author' => $this->mapAuthor($payload),
            'work' => $this->mapWork($payload),
            'edition' => $this->mapEdition($payload),
            default => null,
        };
    }

    // -------------------------------------------------------------------------
    // Author record (e.g. /authors/OL34184A)
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>  $p */
    private function mapAuthor(array $p): ?NormalisedRecord
    {
        $name = $p['name'] ?? $p['personal_name'] ?? null;
        if (! is_string($name) || trim($name) === '') {
            return null;
        }

        return new NormalisedRecord(
            author: $this->buildAuthorFromOlPayload($p, role: 'author'),
        );
    }

    // -------------------------------------------------------------------------
    // Work record (e.g. /works/OL45804W)
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>  $p */
    private function mapWork(array $p): ?NormalisedRecord
    {
        $title = $p['title'] ?? null;
        if (! is_string($title) || trim($title) === '') {
            return null;
        }

        $authors = $this->resolveAuthorRefs($p['authors'] ?? []);
        $description = $this->extractTextual($p['description'] ?? null);

        $work = new NormalisedWork(
            originalTitle: trim($title),
            // OL work records don't carry a language; default to English (OL is English-primary).
            // The companion editions disambiguate via their own languages[].
            originalLanguage: 'eng',
            description: $description,
            firstPublicationYear: $this->yearFromString($p['first_publish_date'] ?? null),
            wikidataId: $this->wikidataFromRemoteIds($p['remote_ids'] ?? null),
            authors: $authors,
            lcshSubjects: $this->subjects($p['subjects'] ?? []),
            sourceAuthorityId: $this->stripLeading($p['key'] ?? null),
            sourceSystem: 'openlibrary',
        );

        return new NormalisedRecord(work: $work);
    }

    // -------------------------------------------------------------------------
    // Edition record (e.g. /books/OL7353617M)
    // -------------------------------------------------------------------------

    /** @param  array<string, mixed>  $p */
    private function mapEdition(array $p): ?NormalisedRecord
    {
        $title = $p['title'] ?? null;
        if (! is_string($title) || trim($title) === '') {
            return null;
        }

        $languageCode = IsoLanguage::normalise($this->firstLanguageKey($p['languages'] ?? null));
        $expressionLanguage = $languageCode ?? 'eng';

        // Resolve the work this edition belongs to
        $workOlid = $this->stripLeading($p['works'][0]['key'] ?? null);
        $workRecord = $this->lookupCompanion('works/'.basename((string) $workOlid));
        $work = $workRecord ? $this->mapWork($workRecord->payload)?->work : null;

        // If the work record isn't yet staged, build a minimal work from the edition's own title
        if ($work === null) {
            $work = new NormalisedWork(
                originalTitle: trim($title),
                originalLanguage: $expressionLanguage,
                authors: $this->resolveAuthorRefs($p['authors'] ?? []),
                sourceAuthorityId: $workOlid,
                sourceSystem: 'openlibrary',
            );
        }

        $dateParts = PublicationDateParser::parse($p['publish_date'] ?? null);

        $publisherName = $this->firstString($p['publishers'] ?? null);
        $publisher = $publisherName !== null
            ? new NormalisedPublisher(
                name: $publisherName,
                sourceSystem: 'openlibrary',
            )
            : null;

        $edition = new NormalisedEdition(
            isbn13: $this->firstString($p['isbn_13'] ?? null),
            isbn10: $this->firstString($p['isbn_10'] ?? null),
            publicationDate: $dateParts['date'],
            publicationYear: $dateParts['year'],
            format: $this->guessFormat($p),
            pages: isset($p['number_of_pages']) ? (int) $p['number_of_pages'] : null,
            coverUrl: $this->coverUrl($p['covers'] ?? null),
            publisher: $publisher,
            sourceAuthorityId: $this->stripLeading($p['key'] ?? null),
            sourceSystem: 'openlibrary',
        );

        $expression = new NormalisedExpression(
            language: $expressionLanguage,
            title: trim($title),
            // Edition records don't tell us whether they are a translation;
            // if work's original_language differs, CatalogWriter will reclassify.
            expressionType: 'original',
            contributors: [],
        );

        return new NormalisedRecord(
            work: $work,
            expression: $expression,
            edition: $edition,
        );
    }

    // -------------------------------------------------------------------------
    // OLID-resolution helpers
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, mixed>  $refs  OpenLibrary author refs as they appear on work or edition payloads
     *
     *  Two shapes seen in the wild:
     *      [{"author": {"key": "/authors/OL34184A"}, "type": {...}}]   ← on works
     *      [{"key": "/authors/OL34184A"}]                              ← on editions
     * @return array<int, NormalisedAuthor>
     */
    private function resolveAuthorRefs(array $refs): array
    {
        $authors = [];
        $position = 0;

        foreach ($refs as $ref) {
            $key = $ref['author']['key'] ?? $ref['key'] ?? null;

            if (! is_string($key)) {
                continue;
            }

            $olid = basename($key); // /authors/OL34184A → OL34184A
            $companion = $this->lookupCompanion("authors/{$olid}");

            if ($companion !== null && is_array($companion->payload)) {
                $authors[] = $this->buildAuthorFromOlPayload(
                    $companion->payload,
                    role: 'author',
                    position: $position++,
                );
            } else {
                // Stub: we have the OLID but no name yet. Use the OLID as the display name
                // so downstream review can surface it. Better than dropping the author entirely.
                $authors[] = new NormalisedAuthor(
                    displayName: $olid,
                    role: 'author',
                    position: $position++,
                    sourceAuthorityId: $olid,
                    sourceSystem: 'openlibrary',
                );
            }
        }

        return $authors;
    }

    /**
     * Look up another raw_ingestion_records row by OpenLibrary key (e.g. "authors/OL34184A").
     * Used to enrich author references found on work/edition records.
     */
    private function lookupCompanion(string $sourceId): ?RawIngestionRecord
    {
        return RawIngestionRecord::query()
            ->where('source_system', 'openlibrary')
            ->where('source_record_id', $sourceId)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $p  OL author payload
     */
    private function buildAuthorFromOlPayload(array $p, string $role, int $position = 0): NormalisedAuthor
    {
        $remote = is_array($p['remote_ids'] ?? null) ? $p['remote_ids'] : [];

        return new NormalisedAuthor(
            displayName: trim((string) ($p['name'] ?? $p['personal_name'] ?? '')),
            sortName: isset($p['personal_name']) ? trim((string) $p['personal_name']) : null,
            birthYear: $this->yearFromString($p['birth_date'] ?? null),
            deathYear: $this->yearFromString($p['death_date'] ?? null),
            viafId: isset($remote['viaf']) ? (string) $remote['viaf'] : null,
            isni: isset($remote['isni']) ? str_replace(' ', '', (string) $remote['isni']) : null,
            wikidataId: isset($remote['wikidata']) ? (string) $remote['wikidata'] : null,
            role: $role,
            position: $position,
            alternateNames: array_values(array_filter(
                is_array($p['alternate_names'] ?? null) ? $p['alternate_names'] : [],
                fn ($n) => is_string($n) && trim($n) !== '',
            )),
            sourceAuthorityId: $this->stripLeading($p['key'] ?? null),
            sourceSystem: 'openlibrary',
        );
    }

    // -------------------------------------------------------------------------
    // Field-level helpers
    // -------------------------------------------------------------------------

    /** @param  mixed  $arr */
    private function firstString($arr): ?string
    {
        if (! is_array($arr) || $arr === []) {
            return null;
        }
        foreach ($arr as $v) {
            if (is_string($v) && trim($v) !== '') {
                return trim($v);
            }
        }

        return null;
    }

    /** @param  mixed  $langs */
    private function firstLanguageKey($langs): ?string
    {
        if (! is_array($langs)) {
            return null;
        }
        foreach ($langs as $l) {
            if (is_array($l) && isset($l['key']) && is_string($l['key'])) {
                return $l['key'];
            }
        }

        return null;
    }

    /** @param  array<int, mixed>  $subjects */
    private function subjects(array $subjects): array
    {
        return array_values(array_filter(
            $subjects,
            fn ($s) => is_string($s) && trim($s) !== '',
        ));
    }

    /**
     * OL description can be a plain string OR {type, value}.
     *
     * @param  mixed  $d
     */
    private function extractTextual($d): ?string
    {
        if (is_string($d)) {
            return trim($d);
        }
        if (is_array($d) && isset($d['value']) && is_string($d['value'])) {
            return trim($d['value']);
        }

        return null;
    }

    private function yearFromString(mixed $raw): ?int
    {
        if (! is_string($raw)) {
            return null;
        }

        return PublicationDateParser::parse($raw)['year'];
    }

    /** @param  mixed  $remote */
    private function wikidataFromRemoteIds($remote): ?string
    {
        if (is_array($remote) && isset($remote['wikidata']) && is_string($remote['wikidata'])) {
            return $remote['wikidata'];
        }

        return null;
    }

    /** "/works/OL45804W" → "OL45804W"; falls back to null. */
    private function stripLeading(mixed $key): ?string
    {
        if (! is_string($key) || $key === '') {
            return null;
        }

        return basename($key);
    }

    /** @param  array<string, mixed>  $p */
    private function guessFormat(array $p): string
    {
        $hint = mb_strtolower((string) ($p['physical_format'] ?? ''));

        return match (true) {
            str_contains($hint, 'hardcover'), str_contains($hint, 'hardback') => 'hardcover',
            str_contains($hint, 'ebook'), str_contains($hint, 'electronic') => 'ebook',
            str_contains($hint, 'audio') => 'audiobook',
            default => 'paperback',
        };
    }

    /** @param  mixed  $covers */
    private function coverUrl($covers): ?string
    {
        if (! is_array($covers)) {
            return null;
        }
        foreach ($covers as $id) {
            // OL uses -1 as a "no cover" placeholder.
            if (is_int($id) && $id > 0) {
                return "https://covers.openlibrary.org/b/id/{$id}-L.jpg";
            }
        }

        return null;
    }
}
