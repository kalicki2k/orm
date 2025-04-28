<?php

namespace ORM\Persistence;

use ORM\Entity\EntityBase;
use SplObjectStorage;

class UpdateSchedule implements Schedule
{
    private SplObjectStorage $scheduledForUpdate;

    public function __construct()
    {
        $this->scheduledForUpdate = new SplObjectStorage();
    }

    public function schedule(EntityBase $entity): void
    {
        if (!$this->scheduledForUpdate->contains($entity)) {
            $this->scheduledForUpdate->attach($entity);
        }
    }

    public function contains(EntityBase $entity): bool
    {
        return $this->scheduledForUpdate->contains($entity);
    }

    public function getAll(): SplObjectStorage
    {
        return $this->scheduledForUpdate;
    }

    public function clear(): void
    {
        $this->scheduledForUpdate = new SplObjectStorage();
    }
}