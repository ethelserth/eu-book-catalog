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
 * Maps a BIBLIONET "title" payload into the shared NormalisedRecord shape.
 *
 * Unlike OpenLibrary's separation of work / edition / author records, a single
 * BIBLIONET title carries everything: work-level (originalTitle, languageOriginal),
 * expression-level (title, language), edition-level (isbn, publisher, pages),
 * plus the writer's display name. So every BIBLIONET title produces a full
 * FRBR chain in one go.
 *
 * Payload is the serialised Ethelserth\Biblionet\DTOs\Title (camelCase keys),
 * staged by BiblionetFetch::stageTitle().
 */
final class BiblionetMapper implements MapperInterface
{
    public function sourceSystem(): string
    {
        return 'biblionet';
    }

    public function map(RawIngestionRecord $record): ?NormalisedRecord
    {
        $p = $record->payload;
        if (! is_array($p)) {
            return null;
        }

        $title = isset($p['title']) ? trim((string) $p['title']) : '';
        if ($title === '') {
            return null;
        }

        $expressionLanguage = IsoLanguage::normalise($p['language'] ?? null) ?? 'ell';
        $originalLanguage = IsoLanguage::normalise($p['languageOriginal'] ?? null)
            ?? $expressionLanguage;

        // Did BIBLIONET tell us this is a translation? Two signals:
        //   - languageTranslatedFrom is set
        //   - languageOriginal differs from language
        $isTranslation = ! empty($p['languageTranslatedFrom'])
            || ($originalLanguage !== $expressionLanguage);

        $writer = $this->buildWriter($p);
        $authors = $writer !== null ? [$writer] : [];

        $work = new NormalisedWork(
            originalTitle: trim((string) ($p['originalTitle'] ?? $p['title'])),
            originalLanguage: $originalLanguage,
            description: $this->stringOrNull($p['summary'] ?? null),
            firstPublicationYear: $this->yearFrom($p['firstPublishDate'] ?? null),
            authors: $authors,
            sourceAuthorityId: isset($p['titlesId']) ? (string) $p['titlesId'] : null,
            sourceSystem: 'biblionet',
        );

        $expression = new NormalisedExpression(
            language: $expressionLanguage,
            title: $title,
            expressionType: $isTranslation ? 'translation' : 'original',
            contributors: [],
        );

        $dateRaw = $p['currentPublishDate'] ?? $p['firstPublishDate'] ?? null;
        $dateParts = PublicationDateParser::parse($this->stringOrNull($dateRaw));

        $publisherName = $this->stringOrNull($p['publisher'] ?? null);
        $publisher = $publisherName !== null
            ? new NormalisedPublisher(
                name: $publisherName,
                country: 'GR',  // BIBLIONET is a Greek-publisher catalog
                sourceAuthorityId: isset($p['publisherId']) ? (string) $p['publisherId'] : null,
                sourceSystem: 'biblionet',
            )
            : null;

        $edition = new NormalisedEdition(
            isbn13: $this->normaliseIsbn13($p),
            isbn10: $this->normaliseIsbn10($p),
            publicationDate: $dateParts['date'],
            publicationYear: $dateParts['year'],
            format: 'paperback',
            pages: isset($p['pageNo']) ? (int) $p['pageNo'] : null,
            coverUrl: $this->stringOrNull($p['coverImage'] ?? null),
            publisher: $publisher,
            sourceAuthorityId: isset($p['titlesId']) ? (string) $p['titlesId'] : null,
            sourceSystem: 'biblionet',
        );

        return new NormalisedRecord(
            work: $work,
            expression: $expression,
            edition: $edition,
        );
    }

    /** @param  array<string, mixed>  $p */
    private function buildWriter(array $p): ?NormalisedAuthor
    {
        $name = $this->stringOrNull($p['writerName'] ?? $p['writer'] ?? null);
        if ($name === null) {
            return null;
        }

        return new NormalisedAuthor(
            displayName: $name,
            sortName: $this->stringOrNull($p['writer'] ?? null),
            role: 'author',
            position: 0,
            sourceAuthorityId: isset($p['writerId']) ? (string) $p['writerId'] : null,
            sourceSystem: 'biblionet',
        );
    }

    /** @param  array<string, mixed>  $p */
    private function normaliseIsbn13(array $p): ?string
    {
        foreach (['isbn', 'isbn2', 'isbn3'] as $field) {
            $raw = isset($p[$field]) ? preg_replace('/[^0-9X]/i', '', (string) $p[$field]) : null;
            if ($raw !== null && strlen($raw) === 13) {
                return $raw;
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $p */
    private function normaliseIsbn10(array $p): ?string
    {
        foreach (['isbn', 'isbn2', 'isbn3'] as $field) {
            $raw = isset($p[$field]) ? preg_replace('/[^0-9X]/i', '', (string) $p[$field]) : null;
            if ($raw !== null && strlen($raw) === 10) {
                return $raw;
            }
        }

        return null;
    }

    private function yearFrom(mixed $raw): ?int
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return PublicationDateParser::parse($raw)['year'];
    }

    private function stringOrNull(mixed $v): ?string
    {
        if (! is_string($v)) {
            return null;
        }
        $trim = trim($v);

        return $trim === '' ? null : $trim;
    }
}
