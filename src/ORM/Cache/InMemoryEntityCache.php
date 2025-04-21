<?php

namespace ORM\Cache;

use ORM\Entity\EntityBase;

final class InMemoryEntityCache implements EntityCache
{
    /**
     * @var array<string, array<int|string, EntityBase>>
     */
    private array $entities = [];

    public function has(string $class, int|string $id): bool
    {
        return isset($this->entities[$class][$id]);
    }

    public function get(string $class, int|string $id): ?EntityBase
    {
        return $this->entities[$class][$id] ?? null;
    }

    public function set(string $class, int|string $id, EntityBase $entity): void
    {
        $this->entities[$class][$id] = $entity;
    }

    public function clear(string $class, int|string $id): void
    {
        unset($this->entities[$class][$id]);
    }

    public function clearAll(): void
    {
        $this->entities = [];
    }
}