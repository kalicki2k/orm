<?php

namespace ORM\Hydration;

use DateMalformedStringException;
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
    private ColumnHydrator $columnHydrator;

    /** @var RelationHydrator[] */
    private array $relationHydrators;

    public function __construct(
        private readonly EntityManager $entityManager,
        private readonly MetadataParser $metadataParser,
        private readonly ReflectionCache $reflectionCache,
    ) {
        $this->columnHydrator = new ColumnHydrator($this->reflectionCache);

        // Load default relation hydrators
        $this->relationHydrators = [
            new LazyOneToOneHydrator($this->entityManager),
            new LazyOneToManyHydrator($this->entityManager),
            new EagerOneToOneHydrator($this->entityManager),
            new EagerOneToManyHydrator($this->entityManager, $this->reflectionCache, $this->metadataParser),
        ];
    }

    /**
     * Hydrates a single entity instance from its metadata and a row of SQL data.
     *
     * @param MetadataEntity $metadata The metadata of the entity to hydrate.
     * @param array $row The aliased SQL row result.
     * @return EntityBase The fully hydrated entity.
     * @throws ReflectionException|DateMalformedStringException
     */
    public function hydrate(MetadataEntity $metadata, array $row): EntityBase
    {
        $entity = $this
            ->reflectionCache
            ->getClass($metadata->getEntityName())
            ->newInstanceWithoutConstructor();

        $this->columnHydrator->hydrate($entity, $metadata, $row);
        $this->hydrateRelations($entity, $metadata, $row);

        $entity->__takeSnapshot($this->metadataParser->extract($entity));

        return $entity;
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
     * @param array $row The current SQL result row with aliased columns.
     * @throws ReflectionException
     */
    public function hydrateRelations(EntityBase $entity, MetadataEntity $metadata, array $row): void
    {

        foreach ($metadata->getRelations() as $property => $relation) {
            foreach ($this->relationHydrators as $hydrator) {
                if (!$hydrator->supports($relation)) {
                    continue;
                }

                $related = $hydrator->hydrate($entity, $metadata, $property, $relation, $row);
                $this->reflectionCache->getProperty($entity, $property)->setValue($entity, $related);
                break;
            }
        }
    }
}
