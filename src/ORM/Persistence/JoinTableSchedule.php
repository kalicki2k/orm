<?php

namespace ORM\Persistence;

use ORM\Attributes\ManyToMany;
use ORM\Cache\ReflectionCache;
use ORM\Collection;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use SplObjectStorage;

class JoinTableSchedule implements Schedule
{
    private SplObjectStorage $scheduledForInsert;
    private SplObjectStorage $scheduledForUpdate;

    public function __construct(private readonly MetadataParser $metadataParser)
    {
        $this->scheduledForInsert = new SplObjectStorage();
        $this->scheduledForUpdate = new SplObjectStorage();
    }

    public function schedule(EntityBase $entity): void
    {
        $metadata = $this->metadataParser->parse($entity::class);

        foreach ($metadata->getRelations() as $property => $relation) {
            if ($relation["relation"] instanceof ManyToMany && isset($relation["joinTable"])) {
                $reflectionProperty = $this->metadataParser->getReflectionCache()->getProperty($entity, $property);
                /** @var Collection $collection */
                $collection = $reflectionProperty->getValue($entity);

                //                $data = $this->metadataParser->extract($entity);

                var_dump($collection);
            }
        }
    }

    public function contains(EntityBase $entity): bool
    {
        return false;
    }

    public function getAll(): SplObjectStorage|array
    {
        return [];
    }

//    public function getScheduledForInsert(): SplObjectStorage
//    {
//        return $this->scheduledForInsert;
//    }
//
//    public function getScheduledForUpdate(): SplObjectStorage
//    {
//        return $this->scheduledForUpdate;
//    }
//
//    public function scheduleForInsert(EntityBase $entity): void
//    {
//        $this->scheduledForInsert = new SplObjectStorage();
//        var_dump($entity);
//    }

    public function clear(): void
    {
        $this->scheduledForInsert = new SplObjectStorage();
        $this->scheduledForUpdate = new SplObjectStorage();
    }
}