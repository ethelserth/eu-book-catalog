<?php

declare(strict_types=1);

namespace App\Mappers;

use App\DTOs\Normalised\NormalisedRecord;
use App\Models\RawIngestionRecord;

/**
 * Every provider's mapper implements this contract.
 *
 * Why? Field names differ wildly between providers:
 *   - OpenLibrary edition:  isbn_13[]  publishers[]  works[].key  authors[].key
 *   - BIBLIONET title:      ISBN  Publisher  Writer  Title  CurrentPublishDate
 *
 * Each mapper translates *its* provider's vocabulary into the shared
 * NormalisedRecord shape. Once translated, CatalogWriter never needs to know
 * which provider the data came from.
 *
 * This is the Strategy design pattern.
 */
interface MapperInterface
{
    public function sourceSystem(): string;

    /**
     * Translate a raw_ingestion_records row into one normalised record.
     *
     * Returns null if the record cannot be mapped (e.g. payload missing
     * required fields, redirect/delete tombstones, unsupported record_type).
     */
    public function map(RawIngestionRecord $record): ?NormalisedRecord;
}
