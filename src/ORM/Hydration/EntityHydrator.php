<?php

namespace ORM\Hydration;

use DateTimeImmutable;
use ORM\Cache\EntityCache;
use ORM\Cache\ReflectionCache;
use ORM\Entity\EntityBase;
use ORM\Entity\EntityManager;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;

class EntityHydrator implements Hydrator
{
    /** @var RelationHydrator[] */
    private array $relationHydrators;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly MetadataParser $metadataParser,
        private readonly ReflectionCache $reflectionCache,
        private readonly EntityCache $entityCache,
    ) {
        $this->relationHydrators = [
            new LazyOneToOneHydrator($this->entityManager),
            new EagerOneToOneHydrator($this->entityManager),
        ];
    }

    public function hydrate(MetadataEntity $metadata, array $data): EntityBase
    {
        $entity = $this
            ->reflectionCache
            ->getClass($metadata->getEntityName())
            ->newInstanceWithoutConstructor();

        $this->hydrateColumns($entity, $metadata, $data);
        $this->hydrateRelations($entity, $metadata, $data);

        $entity->__takeSnapshot($this->metadataParser->extract($entity));

        $id = $this->reflectionCache->getValue($entity, $metadata->getPrimaryKey());
        if (is_scalar($id)) {
            $this->entityCache->set($metadata->getEntityName(), $id, $entity);
        }

        return $entity;
    }

    private function hydrateColumns(EntityBase $entity, MetadataEntity $metadata, array $data): void
    {
        foreach ($metadata->getColumns() as $property => $column) {
            $name = "{$metadata->getAlias()}_{$column["name"]}";

            if (!array_key_exists($name, $data)) {
                continue;
            }

            $value = $this->hydrateColumn($data[$name], $column["type"] ?? null);
            $this->reflectionCache->getProperty($entity, $property)->setValue($entity, $value);

        }
    }

    private function hydrateColumn(mixed $value, ?string $type = null): mixed
    {
        if (!isset($type)) {
            return $value;
        }

        return match (strtolower($type)) {
            "int", "integer" => (int) $value,
            "float", "double" => (float) $value,
            "bool", "boolean" => (bool) $value,
            "datetime" => new DateTimeImmutable($value),
            "json" => json_decode($value, true),
            default => $value,
        };
    }

    private function hydrateRelations(EntityBase $entity, MetadataEntity $metadata, array $data): void
    {
        foreach ($metadata->getRelations() as $property => $relation) {
            $related = $this->hydrateRelation($metadata, $property, $relation, $data);

            if ($related !== null) {
                $this->reflectionCache->getProperty($entity, $property)->setValue($entity, $related);

            }
        }
    }

    private function hydrateRelation(
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $data
    ): ?object
    {
        foreach ($this->relationHydrators as $hydrator) {
            if ($hydrator->supports($relation)) {
                return $hydrator->hydrate($parentMetadata, $property, $relation, $data);
            }
        }

        return null;
    }
}