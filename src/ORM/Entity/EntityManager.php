<?php

namespace ORM\Entity;

use DateMalformedStringException;
use Generator;
use InvalidArgumentException;
use ORM\Cache\ReflectionCache;
use ORM\Collection;
use ORM\Drivers\DatabaseDriver;
use ORM\Hydration\EntityHydrator;
use ORM\Hydration\Hydrator;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;
use ORM\Query\Expression;
use ORM\Query\QueryBuilder;
use ORM\UnitOfWork;
use Psr\Log\LoggerInterface;
use ReflectionException;

/**
 * The central access point for ORM operations on entities.
 *
 * The EntityManager handles querying, persisting, updating, and deleting entities.
 * It coordinates the UnitOfWork, Hydrator, QueryBuilder, and caching mechanisms
 * to provide a clean and consistent API for interacting with the database.
 *
 * Features:
 * - Cascading persistence and deletion
 * - Lazy and Eager loading of relations
 * - Streaming, batch processing, and snapshot tracking
 * - Identity map and metadata caching
 *
 * @example
 * $user = $entityManager->findBy(User::class, 1);
 * $entityManager->persist($user);
 * $entityManager->flush();
 *
 * @package ORM\Entity
 */
class EntityManager {
    /**
     * Handles the tracking and execution of insert, update, and delete operations.
     *
     * The UnitOfWork manages entity state transitions and cascades,
     * and coordinates the actual SQL execution via the relevant executors.
     */
    private UnitOfWork $unitOfWork;

    /**
     * Provides cached reflection access to classes and properties.
     *
     * This cache avoids repeated calls to PHP's Reflection API and is used
     * throughout metadata parsing, hydration, and entity lifecycle operations.
     */
    private ReflectionCache $reflectionCache;

    /**
     * Responsible for hydrating entities from raw SQL result data.
     *
     * Delegates column and relation hydration to specialized strategies
     * such as Lazy/Eager loading via RelationHydrators.
     */
    private Hydrator $hydrator;

    /**
     * Creates a new EntityManager instance.
     *
     * Sets up all core dependencies like UnitOfWork, Hydrator, and caches.
     * Enables fluent querying, persistence, hydration, and change tracking of entities.
     *
     * @param DatabaseDriver $databaseDriver The database driver used for query execution (e.g. PDO).
     * @param MetadataParser $metadataParser Parses attribute-based entity metadata.
     * @param LoggerInterface|null $logger Optional PSR-3 logger for debugging or query logging.
     */
    public function __construct(
        private readonly DatabaseDriver $databaseDriver,
        private readonly MetadataParser $metadataParser,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->reflectionCache = $metadataParser->getReflectionCache();
        $this->unitOfWork = new UnitOfWork($this->databaseDriver, $this->metadataParser, $this->logger);
        $this->hydrator = new EntityHydrator($this, $this->metadataParser, $this->reflectionCache);
    }

    /**
     * Marks one or multiple new entities for insertion.
     *
     * Entities scheduled via `persist()` will be inserted into the database
     * during `flush()`. This supports both individual and batch inserts.
     *
     * Example:
     * ```php
     * $user = new User();
     * $user->setUsername("john");
     * $entityManager->persist($user)->flush();
     *
     * // Multiple entities
     * $entityManager->persist([$user1, $user2])->flush();
     * ```
     *
     * @param EntityBase|array<EntityBase> $entity One or more new entities to persist.
     *
     * @return $this Fluent interface for method chaining.
     *
     * @throws ReflectionException If reflection access fails during snapshot creation.
     * @throws InvalidArgumentException If array contains non-EntityBase values.
     *
     * @example
     * $user = new User();
     * $user->setUsername("john");
     * $entityManager->persist($user)->flush();
     *
     * // Multiple entities
     * $entityManager->persist([$user1, $user2])->flush();
     */
    public function persist(EntityBase|array $entity): self
    {
        if (is_array($entity)) {
            foreach ($entity as $value) {
                if (!($value instanceof EntityBase)) {
                    throw new InvalidArgumentException("Expected instance of EntityBase.");
                }
                $this->persist($value);
            }
            return $this;
        }

        $this->unitOfWork->scheduleForInsert($entity);
        return $this;
    }

    /**
     * Schedules one or multiple entities for update.
     *
     * Accepts either a single entity or an array of entities. Only entities marked
     * as dirty (changed) will result in actual SQL UPDATE statements during `flush()`.
     *
     * Example:
     * ```php
     * $user->setEmail("new@example.com");
     * $entityManager->update($user)->flush();
     *
     * // Batch update
     * $entityManager->update([$user1, $user2])->flush();
     * ```
     *
     * @param EntityBase|array<EntityBase> $entity One or more entities to mark for update.
     *
     * @return $this Fluent interface for method chaining.
     *
     * @throws ReflectionException If property access fails during change detection.
     * @throws InvalidArgumentException If array contains non-EntityBase values.
     *
     * @example
     * $user->setEmail("new@example.com");
     * $entityManager->update($user)->flush();
     *
     * // Batch update
     * $entityManager->update([$user1, $user2])->flush();
     */
    public function update(EntityBase|array $entity): self
    {
        if (is_array($entity)) {
            foreach ($entity as $value) {
                if (!($value instanceof EntityBase)) {
                    throw new InvalidArgumentException("Expected instance of EntityBase.");
                }
                $this->update($value);
            }
            return $this;
        }

        $this->unitOfWork->scheduleForUpdate($entity);
        return $this;
    }

    /**
     * Schedules one or multiple entities for deletion.
     *
     * Supports single entity instance or an array of entities. The actual deletion
     * is deferred until `flush()` is called. If the array contains non-entity values,
     * an InvalidArgumentException is thrown.
     *
     * @param EntityBase|array<EntityBase> $entity The entity or list of entities to delete.
     *
     * @return $this Fluent interface for method chaining.
     *
     * @throws ReflectionException If reflection fails while inspecting the entity.
     * @throws InvalidArgumentException If array contains non-EntityBase elements.
     *
     * @example
     * $entityManager->delete($user);
     * $entityManager->delete([$user1, $user2]);
     * $entityManager->flush();
     */
    public function delete(EntityBase|array $entity): self
    {
        if (is_array($entity)) {
            foreach ($entity as $value) {
                if (!($value instanceof EntityBase)) {
                    throw new InvalidArgumentException("Expected instance of EntityBase.");
                }
                $this->delete($value);
            }
            return $this;
        }

        $this->unitOfWork->scheduleForDelete($entity);
        return $this;
    }

    /**
     * Finds all entities of a given type that match the provided criteria.
     *
     * Executes a SELECT query using metadata and returns a collection of hydrated entities.
     * Entity hydration is handled via the EntityHydrator, including columns and relations.
     * Entities are cached by ID in the identity map (EntityCache) to ensure uniqueness per request.
     *
     * @template T of EntityBase
     *
     * @param class-string<T> $entityClass Fully-qualified class name of the entity (e.g. User::class).
     * @param Expression|int|string|array|null $criteria Optional WHERE clause (e.g. ['status' => 'active']).
     * @param array $options Optional query options (e.g. 'limit', 'offset', 'orderBy', 'joins').
     *
     * @return Collection<T> Collection of hydrated entities of type T.
     *
     * @throws ReflectionException If metadata or reflection fails.
     * @throws DateMalformedStringException If date/time conversion fails during hydration.
     */
    public function findAll(string $entityClass, Expression|int|string|array|null $criteria = null, array $options = []): Collection
    {
        $metadata = $this->getMetadata($entityClass);

        $rows = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata($metadata, fn(string $class) => $this->getMetadata($class), [], $this->normalizeCriteria($criteria, $metadata), $options)
            ->execute()
            ->fetchAll();

        $primaryKeyColumn = "{$metadata->getAlias()}_{$metadata->getPrimaryKey()}";
        $grouped = [];

        foreach ($rows as $row) {
            $primaryKey = $row[$primaryKeyColumn];
            $grouped[$primaryKey][] = $row;
        }

        $entities = [];
        foreach ($grouped as $block) {
            $entity   = $this->hydrateEntity($metadata, array_shift($block));

            foreach ($block as $row) {
                $this->hydrator->hydrateRelations($entity, $metadata, $row);
            }

            $entities[] = $entity;
        }

        return new Collection($entities);
    }

    /**
     * Finds and returns a single entity instance by primary key or complex criteria.
     *
     * Supports:
     * - Scalar primary key lookup (e.g. 123)
     * - Associative array criteria (e.g. ['email' => 'foo@bar.com'])
     * - Expression tree (via ExpressionBuilder)
     *
     * If the entity was already hydrated and cached (via EntityCache), it is returned directly.
     * Otherwise, the method executes a SELECT and hydrates the entity from the first matching row.
     *
     * Note: In case of EAGER OneToMany relations, only the *last* row is used to construct the base entity.
     * Hydration strategies for collections are handled inside the EntityHydrator.
     *
     * @param class-string<EntityBase> $entityName Fully-qualified class name of the entity (e.g. User::class).
     * @param Expression|int|string|array|null $criteria Optional criteria or primary key value.
     * @param array $options Optional query settings (e.g. joins, orderBy, limit).
     *
     * @return EntityBase|null Hydrated entity instance or null if no result was found.
     *
     * @throws DateMalformedStringException If date/time conversion fails during hydration.
     * @throws ReflectionException If metadata or reflection fails.
     */
    public function findBy(string $entityName, Expression|int|string|array|null $criteria = null, array $options = []): ?EntityBase
    {
        $metadata = $this->getMetadata($entityName);
        $rows = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata(
                $metadata,
                fn(string $class) => $this->getMetadata($class),
                [],
                $this->normalizeCriteria($criteria, $metadata),
                $options,
            )
            ->execute()
            ->fetchAll();

        if (!$rows) {
            return null;
        }

        $entity   = $this->hydrateEntity($metadata, array_shift($rows));

        foreach ($rows as $row) {
            $this->hydrator->hydrateRelations($entity, $metadata, $row);
        }

        return $entity;
    }

    /**
     * Streams all entities of the given type using a generator.
     *
     * This method is useful for memory-efficient iteration over large datasets.
     * All records from the table will be hydrated and yielded one-by-one.
     *
     * @param string $entityName The fully-qualified class name of the entity to stream.
     * @param array $options Optional query options (e.g. joins, ordering, limits).
     *
     * @return Generator<EntityBase> A generator yielding hydrated entity instances.
     *
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public function streamAll(string $entityName, array $options = []): Generator
    {
        return $this->streamInternal($entityName, null, $options);
    }

    /**
     * Streams entities from the database matching the given criteria using a generator.
     *
     * This method is ideal for processing large result sets efficiently without loading all entities into memory.
     * Each matching row is hydrated and yielded as an entity instance.
     *
     * @param string $entityName The fully-qualified class name of the entity.
     * @param Expression|int|string|array|null $criteria Optional filtering criteria.
     *        - Scalar (int|string): treated as lookup by primary key.
     *        - Array: treated as key-value conditions (e.g. ['type' => 'admin']).
     *        - Expression: for advanced query logic (AND/OR groups etc.).
     * @param array $options Optional query options (e.g. joins, ordering, limits).
     *
     * @return Generator<EntityBase> A generator yielding hydrated entity instances.
     *
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public function streamBy(string $entityName, Expression|int|string|array|null $criteria = null, array $options = []): Generator
    {
        return $this->streamInternal($entityName, $criteria, $options);
    }

    /**
     * Counts the number of rows matching the given criteria for the specified entity.
     *
     * This is equivalent to running `SELECT COUNT(*)` on the corresponding entity table,
     * with optional filtering and SQL options (e.g. joins, where conditions).
     *
     * @param string $entityName The fully-qualified class name of the entity.
     * @param Expression|int|string|array|null $criteria Optional filtering criteria.
     *        - Scalar (int|string): treated as lookup by primary key.
     *        - Array: treated as key-value conditions (e.g. ['status' => 'active']).
     *        - Expression: allows advanced expressions via ExpressionBuilder.
     * @param array $options Optional additional options (e.g. joins, groupBy).
     *
     * @return int The number of rows matching the query.
     *
     * @throws ReflectionException If metadata resolution fails.
     */
    public function countBy(
        string $entityName,
        Expression|int|string|array|null
        $criteria = null,
        array $options = []
    ): int
    {
        $metadata = $this->getMetadata($entityName);
        $statement = new QueryBuilder($this->databaseDriver, $this->logger)
            ->count()
            ->fromMetadata(
                $metadata,
                null, [],
                $this->normalizeCriteria($criteria, $metadata),
                $options,
            )
            ->execute();

        $result = $statement->fetch();
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Flushes all pending changes to the database.
     *
     * Executes all scheduled insert, update, and delete operations in the correct order,
     * respecting cascade rules and entity dependencies.
     *
     * - Inserts are sorted by dependency (e.g. children wait for parents).
     * - Cascade operations (Persist, Remove) are honored.
     * - Tracks entity state via UnitOfWork and resets after flush.
     *
     * @throws ReflectionException If metadata reflection fails during flush.
     */
    public function flush(): void
    {
        $this->unitOfWork->commit();
    }

    /**
     * Retrieves the parsed metadata for a given entity class.
     *
     * This method returns the structural mapping of the specified entity, including:
     * - Table name
     * - Primary key definition
     * - Column mappings
     * - Relationship mappings (OneToOne, OneToMany, etc.)
     *
     * Metadata is cached internally to avoid repeated reflection lookups.
     *
     * @param string $entityName The fully qualified class name of the entity.
     *
     * @return MetadataEntity The metadata describing the structure and mappings of the entity.
     *
     * @throws ReflectionException If reflection fails during metadata parsing.
     */
    public function getMetadata(string $entityName): MetadataEntity
    {
        return $this->metadataParser->parse($entityName);
    }

    /**
     * Normalizes different input formats for criteria into a consistent array or Expression.
     *
     * Accepts primary key scalar (e.g. ID), associative arrays (e.g. ['email' => '...']),
     * or Expression objects for advanced conditions. This ensures that `findBy()`, `findAll()`, etc.
     * can be called flexibly while still being resolved to a valid internal query structure.
     *
     * @param Expression|int|string|array|null $criteria The raw criteria input from the user.
     * @param MetadataEntity $metadata Metadata used to determine the primary key for scalar lookups.
     *
     * @return Expression|array The normalized criteria ready for query building.
     */
    private function normalizeCriteria(Expression|int|string|array|null $criteria, MetadataEntity $metadata): Expression|array
    {
        if ($criteria instanceof Expression) {
            return $criteria;
        }

        if (is_null($criteria)) {
            return [];
        }

        if (is_array($criteria)) {
            return $criteria;
        }

        return [$metadata->getPrimaryKey() => $criteria];
    }

    /**
     * Hydrates a new entity instance from a single SQL result row using the given metadata.
     *
     * Internally delegates to the configured `EntityHydrator` and handles hydration of both
     * scalar columns and relational properties (e.g. OneToOne, OneToMany, Lazy/Eager).
     *
     * If the entity has already been loaded before (based on primary key), it will return the
     * cached instance from the identity map (`EntityCache`).
     *
     * @param MetadataEntity $metadata The parsed metadata for the entity being hydrated.
     * @param array $data An associative SQL result row with aliased column names.
     *
     * @return EntityBase A fully hydrated entity instance.
     *
     * @throws DateMalformedStringException If a datetime column contains an invalid format.
     * @throws ReflectionException If a reflection call fails during hydration.
     */
    public function hydrateEntity(MetadataEntity $metadata, array $data): EntityBase
    {
        return $this->hydrator->hydrate($metadata, $data);
    }

    /**
     * Internal generator used by `streamAll()` and `streamBy()` to yield entities from the database.
     *
     * Applies optional filtering (`$criteria`) and query configuration (`$options`) while streaming
     * results row-by-row. Uses the identity map (`EntityCache`) to avoid hydrating duplicates.
     *
     * @template T of EntityBase
     *
     * @param class-string<T> $entityName Fully qualified class name of the entity.
     * @param Expression|int|string|array|null $criteria Filtering conditions (WHERE).
     * @param array $options Optional query options (joins, limit, orderBy, etc.).
     *
     * @return Generator<T> A generator yielding hydrated entity instances.
     *
     * @throws ReflectionException If metadata or reflection fails.
     * @throws DateMalformedStringException On invalid datetime format in hydration.
     */
    private function streamInternal(
        string $entityName,
        Expression|int|string|array|null $criteria = null,
        array $options = [],
    ): Generator
    {
        $currentId = null;
        $group = [];
        $metadata = $this->getMetadata($entityName);
        $primaryKeyColumn = "{$metadata->getAlias()}_{$metadata->getPrimaryKey()}";

        $statement = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata(
                $metadata,
                fn(string $class) => $this->getMetadata($class),
                [],
                $this->normalizeCriteria($criteria, $metadata),
                $options
            )
            ->execute();

        while ($row = $statement->fetch()) {
            $id = $row[$primaryKeyColumn];

            if ($currentId !== null && $id !== $currentId) {
                $entity = $this->hydrateEntity($metadata, array_shift($group));

                foreach ($group as $extraRow) {
                    $this->hydrator->hydrateRelations($entity, $metadata, $extraRow);
                }

                yield $entity;
                $group = [];
            }

            $currentId = $id;
            $group[] = $row;
        }

        if (!empty($group)) {
            $entity = $this->hydrateEntity($metadata, array_shift($group));
            foreach ($group as $extraRow) {
                $this->hydrator->hydrateRelations($entity, $metadata, $extraRow);
            }
            yield $entity;
        }
    }
}
