<?php

namespace ORM\Cache;

use ORM\Metadata\MetadataEntity;
class InMemoryMetadataCache implements MetadataCacheInterface
{
    private array $cache = [];

    public function get(string $key): ?MetadataEntity
    {
        return $this->cache[$key] ?? null;
    }

    public function set(string $key, MetadataEntity $entity): void
    {
        $this->cache[$key] = $entity;
    }

    public function clear(string $key): void
    {
        unset($this->cache[$key]);
    }
}
