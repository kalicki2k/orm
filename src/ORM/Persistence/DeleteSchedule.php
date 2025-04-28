<?php

namespace ORM\Persistence;

use ORM\Entity\EntityBase;
use SplObjectStorage;

class DeleteSchedule
{
    private SplObjectStorage $scheduledForDelete;

    public function __construct()
    {
        $this->scheduledForDelete = new SplObjectStorage();
    }

    public function schedule(EntityBase $entity): void
    {
        if (!$this->scheduledForDelete->contains($entity)) {
            $this->scheduledForDelete->attach($entity);
        }
    }

    public function contains(EntityBase $entity): bool
    {
        return $this->scheduledForDelete->contains($entity);
    }

    public function getAll(): SplObjectStorage
    {
        return $this->scheduledForDelete;
    }

    public function clear(): void
    {
        $this->scheduledForDelete = new SplObjectStorage();
    }
}