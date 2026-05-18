<?php

declare(strict_types=1);

namespace App\DTOs\Normalised;

final readonly class NormalisedPublisher
{
    public function __construct(
        public string $name,
        public ?string $country = null,
        public ?string $isni = null,
        public ?string $website = null,
        public ?string $sourceAuthorityId = null,
        public ?string $sourceSystem = null,
    ) {}
}
