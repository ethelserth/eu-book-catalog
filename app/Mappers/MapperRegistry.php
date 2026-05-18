<?php

declare(strict_types=1);

namespace App\Mappers;

use RuntimeException;

/**
 * Looks up the right mapper for a given source_system.
 *
 * Registered as a singleton in AppServiceProvider. Adding a new provider means
 * registering one new MapperInterface implementation in that bind callback.
 */
final class MapperRegistry
{
    /** @var array<string, MapperInterface> */
    private array $mappers = [];

    public function register(MapperInterface $mapper): void
    {
        $this->mappers[$mapper->sourceSystem()] = $mapper;
    }

    public function for(string $sourceSystem): MapperInterface
    {
        if (! isset($this->mappers[$sourceSystem])) {
            throw new RuntimeException("No mapper registered for source_system '{$sourceSystem}'.");
        }

        return $this->mappers[$sourceSystem];
    }

    public function has(string $sourceSystem): bool
    {
        return isset($this->mappers[$sourceSystem]);
    }

    /** @return array<string> */
    public function registeredSourceSystems(): array
    {
        return array_keys($this->mappers);
    }
}
