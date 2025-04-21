<?php

namespace ORM\Cache;

use ORM\Entity\EntityBase;

final class InMemoryEntityCache implements EntityCache
{
    private array $identityMap = [];

    public function get(string $class, int|string $id): ?EntityBase
    {
        return $this->identityMap[$class][$id] ?? null;
    }

    public function set(string $class, int|string $id, EntityBase $entity): void
    {
        $this->identityMap[$class][$id] = $entity;
    }

    public function has(string $class, int|string $id): bool
    {
        return isset($this->identityMap[$class][$id]);
    }

    public function clear(string $class, int|string $id): void
    {
        unset($this->identityMap[$class][$id]);
    }

    public function clearAll(): void
    {
        $this->identityMap = [];
    }
}