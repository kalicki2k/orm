<?php

namespace ORM\Hydration;

use Closure;
use DateMalformedStringException;
use DateTimeImmutable;
use ORM\Attributes\OneToMany;
use ORM\Cache\EntityCache;
use ORM\Cache\ReflectionCache;
use ORM\Collection;
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
            new LazyOneToManyHydrator($this->entityManager),
            new EagerOneToOneHydrator($this->entityManager),
            new EagerOneToManyHydrator($this->entityManager),
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
        $idField = "{$metadata->getAlias()}_{$metadata->getPrimaryKey()}";
        $id = $data[$idField] ?? null;

        if ($id !== null && $this->entityCache->has($metadata->getEntityName(), $id)) {
            $entity = $this->entityCache->get($metadata->getEntityName(), $id);
            $this->hydrateRelations($entity, $metadata, $data);
            return $entity;
        }

        // New entity case (not yet cached)
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
     * @throws DateMalformedStringException|ReflectionException
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
     * Hydrates relational properties (e.g. OneToOne, OneToMany) from SQL result data into an entity.
     *
     * Delegates to appropriate RelationHydrators (e.g. Lazy/Eager) depending on the fetch strategy.
     * Handles deferred loading via Closures and supports Collection merging for JOINed OneToMany relations.
     *
     * - OneToOne (eager): sets the related entity directly.
     * - OneToOne (lazy): stores Closure for deferred lookup via getter.
     * - OneToMany (eager): adds each related entity into the Collection (multi-row JOIN).
     * - OneToMany (lazy): stores Closure returning Collection via finder method.
     * - First JOIN row: initializes the Collection if not already set.
     *
     * @param EntityBase $entity The entity instance to hydrate.
     * @param MetadataEntity $metadata Metadata for the given entity class.
     * @param array $data The current SQL result row with aliased columns.
     * @throws ReflectionException
     */
    public function hydrateRelations(EntityBase $entity, MetadataEntity $metadata, array $data): void
    {
        foreach ($metadata->getRelations() as $property => $relation) {
            $related = $this->hydrateRelation($metadata, $property, $relation, $data);

            if ($related === null) {
                continue;
            }

            if (
                $this->reflectionCache->hasProperty($entity, $property)
                && $this->reflectionCache->isInitialized($entity, $property)
            ) {
                $existing = $this->reflectionCache->getValue($entity, $property);

                if ($existing instanceof Collection && $related instanceof EntityBase) {
                    $existing->add($related);
                    continue;
                }
            }

            if ($related instanceof EntityBase && $relation["relation"] instanceof OneToMany) {
                $this->reflectionCache->setValue($entity, $property, new Collection([$related]));
                continue;
            }

            $this->reflectionCache->getProperty($entity, $property)->setValue($entity, $related);
        }
    }

    /**
     * Resolves and hydrates a single relational property using the appropriate RelationHydrator.
     *
     * This method selects a suitable RelationHydrator based on relation type (e.g. OneToOne, OneToMany)
     * and fetch strategy (Lazy or Eager), and delegates hydration to it.
     *
     * Return types by strategy:
     * - OneToOne (eager): Returns the related EntityBase or null.
     * - OneToOne (lazy): Returns a Closure|null to lazily load the related entity.
     * - OneToMany (eager): Returns one EntityBase per JOIN row (collected into a Collection externally).
     * - OneToMany (lazy): Returns a Closure that resolves to a Collection of related entities.
     *
     * @param MetadataEntity $parentMetadata Metadata of the owning entity (e.g. User).
     * @param string $property The property name to hydrate (e.g. "profile", "posts").
     * @param array $relation Relation metadata as parsed by MetadataParser.
     * @param array $data Current SQL result row (with aliased JOIN columns).
     * @return Closure|Collection|EntityBase|null Hydrated relation value or deferred Closure.
     */
    private function hydrateRelation(
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $data
    ): Closure|Collection|EntityBase|null {
        foreach ($this->relationHydrators as $hydrator) {
            if ($hydrator->supports($relation)) {
                return $hydrator->hydrate($parentMetadata, $property, $relation, $data);
            }
        }

        return null;
    }
}