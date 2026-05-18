<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Our schema stores ISO 639-2 three-letter language codes (`works.original_language`,
 * `expressions.language` are CHAR(3)).
 *
 * OpenLibrary already gives us /languages/eng → 'eng'. BIBLIONET gives full names
 * ("Ελληνικά", "English", "Αγγλικά"). This class normalises whatever the source
 * supplies into a 3-letter code, or null when we can't tell.
 *
 * Why ISO 639-2/B? Better coverage than the 2-letter ISO 639-1 (which lacks codes
 * for many smaller languages) and is the de-facto library/MARC standard.
 */
final class IsoLanguage
{
    /**
     * Loose name → 639-2 lookup. Greek labels included because BIBLIONET returns Greek.
     * Add more as we encounter them.
     *
     * @var array<string, string>
     */
    private const NAME_TO_CODE = [
        // Greek
        'ελληνικά' => 'ell',
        'ελληνική' => 'ell',
        'ελληνικα' => 'ell',
        'greek' => 'ell',
        // English
        'english' => 'eng',
        'αγγλικά' => 'eng',
        'αγγλικα' => 'eng',
        // French
        'french' => 'fre',
        'français' => 'fre',
        'γαλλικά' => 'fre',
        'γαλλικα' => 'fre',
        // German
        'german' => 'ger',
        'deutsch' => 'ger',
        'γερμανικά' => 'ger',
        'γερμανικα' => 'ger',
        // Italian
        'italian' => 'ita',
        'italiano' => 'ita',
        'ιταλικά' => 'ita',
        'ιταλικα' => 'ita',
        // Spanish
        'spanish' => 'spa',
        'español' => 'spa',
        'ισπανικά' => 'spa',
        'ισπανικα' => 'spa',
        // Russian
        'russian' => 'rus',
        'ρωσικά' => 'rus',
        'ρωσικα' => 'rus',
    ];

    /** 2-letter → 3-letter for inputs that already look ISO-like. */
    private const ISO1_TO_ISO2 = [
        'en' => 'eng',
        'el' => 'ell',
        'fr' => 'fre',
        'de' => 'ger',
        'it' => 'ita',
        'es' => 'spa',
        'ru' => 'rus',
        'pt' => 'por',
        'nl' => 'dut',
        'pl' => 'pol',
        'tr' => 'tur',
        'ar' => 'ara',
        'zh' => 'chi',
        'ja' => 'jpn',
    ];

    public static function normalise(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        // OpenLibrary key path: "/languages/eng" → "eng"
        if (str_starts_with($raw, '/languages/')) {
            $raw = substr($raw, strlen('/languages/'));
        }

        $clean = mb_strtolower(trim($raw));

        if ($clean === '') {
            return null;
        }

        // Already 3-letter ISO 639-2
        if (preg_match('/^[a-z]{3}$/', $clean)) {
            return $clean;
        }

        // 2-letter ISO 639-1
        if (isset(self::ISO1_TO_ISO2[$clean])) {
            return self::ISO1_TO_ISO2[$clean];
        }

        // Free-text name
        return self::NAME_TO_CODE[$clean] ?? null;
    }
}
