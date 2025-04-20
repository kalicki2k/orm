<?php

namespace ORM\Hydration;

use DateMalformedStringException;
use DateTimeImmutable;
use ORM\Cache\EntityCache;
use ORM\Cache\ReflectionCache;
use ORM\Entity\EntityBase;
use ORM\Entity\EntityManager;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;
use ReflectionException;

/**
 * Hydrates entities from raw SQL result data using metadata and reflection.
 *
 * Handles column hydration (primitive values), as well as relationship hydration
 * via RelationHydrators (e.g. lazy or eager loading strategies).
 *
 * This is a central piece of the ORM that transforms raw SQL rows into real Entity objects.
 *
 * @example
 * ```php
 * $hydratedUser = $entityHydrator->hydrate($userMetadata, $row);
 * ```
 *
 * @see RelationHydrator
 * @see MetadataEntity
 * @see MetadataParser
 */
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
        // Load default relation hydrators
        $this->relationHydrators = [
            new LazyOneToOneHydrator($this->entityManager),
            new EagerOneToOneHydrator($this->entityManager),
        ];
    }

    /**
     * Hydrates a single entity instance from its metadata and a row of SQL data.
     *
     * @param MetadataEntity $metadata The metadata of the entity to hydrate.
     * @param array $data The aliased SQL row result.
     * @return EntityBase The fully hydrated entity.
     * @throws ReflectionException|DateMalformedStringException
     */
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

    /**
     * Hydrates primitive column values from the result set into the entity.
     *
     * Uses alias mapping (e.g., `user_email`) to resolve values.
     * Skips JoinColumns (like `profile_id`) if no matching property exists,
     * since they will be handled later in relation hydration (Lazy/Eager).
     *
     * @param EntityBase $entity The entity being hydrated.
     * @param MetadataEntity $metadata The metadata describing the entity.
     * @param array $data The aliased SQL result row.
     * @throws DateMalformedStringException
     */
    private function hydrateColumns(EntityBase $entity, MetadataEntity $metadata, array $data): void
    {
        foreach ($metadata->getColumns() as $property => $column) {
            $name = "{$metadata->getAlias()}_{$column["name"]}";

            if (!array_key_exists($name, $data)) {
                continue;
            }

            if ($this->reflectionCache->hasProperty($entity, $property)) {
                $value = $this->hydrateColumn($data[$name], $column["type"] ?? null);
                $this->reflectionCache->getProperty($entity, $property)->setValue($entity, $value);
            }
        }
    }

    /**
     * Converts a raw database value to its PHP representation based on the expected column type.
     *
     * This method is used during column hydration to normalize SQL values
     * into proper native PHP types (e.g. DateTime, int, string).
     *
     * @param mixed $value The raw value from the database.
     * @param string|null $type Optional column type hint (e.g. "int", "datetime").
     * @return mixed The hydrated PHP value.
     * @throws DateMalformedStringException
     */
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

    /**
     * Hydrates relational properties (e.g., OneToOne, ManyToOne) for the entity.
     *
     * Delegates hydration logic to appropriate RelationHydrators (e.g. Lazy or Eager).
     *
     * @param EntityBase $entity The entity being hydrated.
     * @param MetadataEntity $metadata Metadata about the current entity.
     * @param array $data The SQL row data (possibly containing aliased JOIN data).
     */
    private function hydrateRelations(EntityBase $entity, MetadataEntity $metadata, array $data): void
    {
        foreach ($metadata->getRelations() as $property => $relation) {
            $related = $this->hydrateRelation($metadata, $property, $relation, $data);

            if ($related !== null) {
                $this->reflectionCache->getProperty($entity, $property)->setValue($entity, $related);

            }
        }
    }

    /**
     * Hydrates a single relational property using the appropriate RelationHydrator.
     *
     * This method delegates the hydration strategy based on the relation's type and fetch mode
     * (e.g. Eager or Lazy). Each RelationHydrator must declare support for a relation before being used.
     *
     * @param MetadataEntity $parentMetadata Metadata of the parent (owning) entity.
     * @param string $property The property name being hydrated (e.g. "profile").
     * @param array $relation The parsed relation metadata (includes relation + joinColumn).
     * @param array $data The SQL result row containing aliased column data.
     * @return object|null The hydrated relation object or null.
     */
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