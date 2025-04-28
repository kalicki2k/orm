<?php

namespace ORM\Persistence;

use ORM\Entity\EntityBase;
use SplObjectStorage;

interface Schedule
{
    public function schedule(EntityBase $entity): void;

    public function contains(EntityBase $entity): bool;

    public function getAll(): SplObjectStorage|array;

    public function clear(): void;
}