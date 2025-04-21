<?php

namespace ORM\Cache;

use ORM\Entity\EntityBase;

interface EntityCache
{
    public function get(string $class, int|string $id): ?EntityBase;
    public function set(string $class, int|string $id, EntityBase $entity): void;
    public function has(string $class, int|string $id): bool;
    public function clear(string $class, int|string $id): void;
    public function clearAll(): void;
}