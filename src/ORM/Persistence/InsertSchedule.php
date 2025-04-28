<?php

namespace ORM\Persistence;

use Closure;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use ReflectionException;
use SplObjectStorage;

class InsertSchedule
{
    private SplObjectStorage $scheduledForInsert;

    public function __construct(private readonly MetadataParser $metadataParser)
    {
        $this->scheduledForInsert = new SplObjectStorage();
    }

    public function schedule(EntityBase $entity): void
    {
        if (!$this->scheduledForInsert->contains($entity)) {
            $this->scheduledForInsert->attach($entity);
        }
    }

    public function contains(EntityBase $entity): bool
    {
        return $this->scheduledForInsert->contains($entity);
    }

    public function getAll(): array
    {
        $ordered = [];
        $visited = [];

        foreach ($this->scheduledForInsert as $entity) {
            $this->visit($entity, $ordered, $visited);
        }

        return $ordered;
    }

    public function clear(): void
    {
        $this->scheduledForInsert = new SplObjectStorage();
    }

    /**
     * @throws ReflectionException
     */
    private function visit(EntityBase $entity, array &$ordered, array &$visited): void
    {
        $hash = spl_object_hash($entity);
        if (isset($visited[$hash])) {
            return;
        }

        $visited[$hash] = true;

        $metadata = $this->metadataParser->parse($entity::class);
        $reflection = $this->metadataParser->getReflectionCache();

        foreach ($metadata->getRelations() as $property => $relationInfo) {
            $related = $reflection->getValue($entity, $property);

            if ($related instanceof Closure) {
                $related = $related();
            }

            if ($related instanceof EntityBase && $this->scheduledForInsert->contains($related)) {
                $this->visit($related, $ordered, $visited);
            }
        }

        $ordered[] = $entity;
    }
}
