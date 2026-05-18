<?php

declare(strict_types=1);

namespace App\DTOs\Normalised;

final readonly class NormalisedEdition
{
    /**
     * @param  string  $format  matches editions.format enum: hardcover|paperback|ebook|audiobook
     */
    public function __construct(
        public ?string $isbn13 = null,
        public ?string $isbn10 = null,
        public ?string $publicationDate = null,
        public ?int $publicationYear = null,
        public string $format = 'paperback',
        public ?int $pages = null,
        public ?string $coverUrl = null,
        public ?NormalisedPublisher $publisher = null,
        public ?string $sourceAuthorityId = null,
        public ?string $sourceSystem = null,
    ) {}
}
