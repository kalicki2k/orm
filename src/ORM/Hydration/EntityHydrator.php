<?php

namespace ORM\Hydration;

use Closure;
use DateMalformedStringException;
use DateTimeImmutable;
use ORM\Attributes\OneToMany;
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
            new EagerOneToManyHydrator($this->entityManager),
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