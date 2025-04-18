<?php

namespace ORM\Cache;

use ORM\Metadata\MetadataEntity;

interface MetadataCacheInterface
{
    /**
     * Fetch a MetadataEntity from cache.
     */
    public function get(string $key): ?MetadataEntity;

    /**
     * Store a MetadataEntity in cache.
     */
    public function set(string $key, MetadataEntity $entity): void;

    /**
     * Remove a MetadataEntity from cache.
     */
    public function clear(string $key): void;
}
