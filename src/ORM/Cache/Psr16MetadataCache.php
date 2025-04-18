<?php

namespace ORM\Cache;

use ORM\Metadata\MetadataEntity;
use Psr\SimpleCache\CacheInterface;

// @todo This is an example and not yet implemented (Symfony, Laravel, Doctrine Cache)
class Psr16MetadataCache implements MetadataCacheInterface
{
    public function __construct(private CacheInterface $cache, private int $ttl = 3600) {}

    public function get(string $key): ?MetadataEntity
    {
        $raw = $this->cache->get($key);
        return $raw instanceof MetadataEntity ? $raw : null;
    }

    public function set(string $key, MetadataEntity $entity): void
    {
        $this->cache->set($key, $entity, $this->ttl);
    }

    public function clear(string $key): void
    {
        $this->cache->delete($key);
    }
}