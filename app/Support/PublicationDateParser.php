<?php

declare(strict_types=1);

namespace App\Support;

/**
 * OpenLibrary publish_date is free text: "1997", "March 1997", "c1997",
 * "circa 1990", "September 15th, 2010", "2010-03-15", etc.
 *
 * BIBLIONET is cleaner ("2010-03-15" or "2010") but still ambiguous.
 *
 * We extract whatever we can. ISO date stays as a string for editions.publication_date;
 * we also pull a 4-digit year for the editions.publication_year integer column,
 * which is what filtering and search actually use.
 */
final class PublicationDateParser
{
    /**
     * @return array{date: ?string, year: ?int}
     *                                          date: ISO 8601 (YYYY-MM-DD) if a full date could be parsed, else null
     *                                          year: any plausible 4-digit publication year, else null
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['date' => null, 'year' => null];
        }

        $clean = trim($raw);

        // Try strict ISO date first: 2010-03-15
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $clean, $m)) {
            return [
                'date' => "{$m[1]}-{$m[2]}-{$m[3]}",
                'year' => self::sanityCheckYear((int) $m[1]),
            ];
        }

        // Try year-month: 2010-03
        if (preg_match('/^(\d{4})-(\d{2})$/', $clean, $m)) {
            return [
                'date' => null,
                'year' => self::sanityCheckYear((int) $m[1]),
            ];
        }

        // Try natural language: "September 15th, 2010", "Mar 1997", "March 1997"
        $parsed = @strtotime($clean);
        if ($parsed !== false) {
            $year = (int) date('Y', $parsed);
            // Only trust the full date if the source clearly contained day+month+year
            // (heuristic: presence of any digit + at least one alpha token of length 3+).
            $hasDayMonth = (bool) preg_match('/\b\d{1,2}\b.*[A-Za-z]{3,}|\b[A-Za-z]{3,}\b.*\b\d{1,2}\b/', $clean);
            $hasYear = (bool) preg_match('/\b(1[5-9]\d{2}|20\d{2})\b/', $clean);

            return [
                'date' => $hasDayMonth && $hasYear ? date('Y-m-d', $parsed) : null,
                'year' => self::sanityCheckYear($year),
            ];
        }

        // Fall back to extracting any 4-digit year token
        if (preg_match('/\b(1[5-9]\d{2}|20\d{2})\b/', $clean, $m)) {
            return [
                'date' => null,
                'year' => self::sanityCheckYear((int) $m[1]),
            ];
        }

        return ['date' => null, 'year' => null];
    }

    private static function sanityCheckYear(int $year): ?int
    {
        // Books exist 1500+; future-dated catalog entries up to next year are plausible.
        $upper = (int) date('Y') + 1;

        return ($year >= 1500 && $year <= $upper) ? $year : null;
    }
}
