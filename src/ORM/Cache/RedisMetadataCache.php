<?php

namespace ORM\Cache;

use ORM\Metadata\MetadataEntity;
use Redis;

readonly class RedisMetadataCache implements MetadataCache
{
    public function __construct(private Redis $redis, private int $ttl = 3600) {}

    public function get(string $key): ?MetadataEntity
    {
        $raw = $this->redis->get($key);
        return $raw ? unserialize($raw) : null;
    }

    public function set(string $key, MetadataEntity $entity): void
    {
        $this->redis->setex($key, $this->ttl, serialize($entity));
    }

    public function clear(string $key): void
    {
        $this->redis->del($key);
    }
}