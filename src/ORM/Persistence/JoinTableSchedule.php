<?php

namespace ORM\Persistence;

use ORM\Attributes\ManyToMany;
use ORM\Collection;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use ReflectionException;

class JoinTableSchedule
{
    private array $scheduledForInsert;
    private array $scheduledForDelete;

    public function __construct(private readonly MetadataParser $metadataParser)
    {
        $this->scheduledForInsert = [];
        $this->scheduledForDelete = [];
    }

    /**
     * @return array
     */
    public function getScheduledForInsert(): array
    {
        return $this->scheduledForInsert;
    }

    /**
     * @return array
     */
    public function getScheduledForDelete(): array
    {
        return $this->scheduledForDelete;
    }

    /**
     * @throws ReflectionException
     */
    public function scheduleForInsert(EntityBase $entity): void
    {
        $this->schedule($entity, $this->scheduledForInsert);
    }

    /**
     * @throws ReflectionException
     */
    public function scheduleForDelete(EntityBase $entity): void
    {
        $this->schedule($entity, $this->scheduledForDelete);
    }

    public function contains(EntityBase $entity, string $property, EntityBase $relatedEntity): bool
    {
        return array_any($this->scheduledForInsert, fn($entry) => $entry["entity"] === $entity
            && $entry["property"] === $property
            && $entry["relatedEntity"] === $relatedEntity
        );
    }

    public function clear(): void
    {
        $this->scheduledForInsert = [];
        $this->scheduledForDelete = [];
    }

    /**
     * @throws ReflectionException
     */
    private function schedule(EntityBase $entity, array &$targetSchedule): void
    {
        $metadata = $this->metadataParser->parse($entity::class);

        foreach ($metadata->getRelations() as $property => $relation) {
            if (!($relation["relation"] instanceof ManyToMany) || !isset($relation["joinTable"])) {
                continue;
            }

            $reflectionProperty = $this->metadataParser->getReflectionCache()->getProperty($entity, $property);
            /** @var Collection $collection */
            $collection = $reflectionProperty->getValue($entity);

            if ($collection->count() === 0) {
                continue;
            }

            foreach ($collection as $relatedEntity) {
                if ($this->contains($entity, $property, $relatedEntity, $targetSchedule)) {
                    continue;
                }

                $targetSchedule[] = [
                    "entity" => $entity,
                    "property" => $property,
                    "relatedEntity" => $relatedEntity
                ];
            }
        }
    }
}