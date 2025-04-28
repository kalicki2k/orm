<?php

namespace ORM\Entity;

use DateMalformedStringException;
use Generator;
use InvalidArgumentException;
use ORM\Attributes\ManyToOne;
use ORM\Attributes\OneToMany;
use ORM\Attributes\OneToOne;
use ORM\Cache\ReflectionCache;
use ORM\Collection;
use ORM\Drivers\DatabaseDriver;
use ORM\Drivers\Statement;
use ORM\Entity\Type\FetchType;
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
        return $this->handleEntities($entity, fn(EntityBase $e) => $this->unitOfWork->scheduleForInsert($e));
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
        return $this->handleEntities($entity, fn(EntityBase $e) => $this->unitOfWork->scheduleForUpdate($e));
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
        return $this->handleEntities($entity, fn(EntityBase $e) => $this->unitOfWork->scheduleForDelete($e));
    }

    /**
     * Retrieves all entities of a given type that match the specified criteria.
     *
     * This method executes a dynamic SELECT query based on entity metadata, optional filtering criteria,
     * and query options like joins, ordering, limits, etc. It supports eager loading of relations via
     * the "joins" option and ensures that each entity is properly hydrated, including its relations.
     *
     * The result is grouped by the primary key to handle joined rows correctly and returned
     * as a Collection of unique, fully hydrated entity instances.
     *
     * Supported criteria formats:
     * - Scalar (int|string): Treated as primary key lookup.
     * - Associative array: Simple key-value WHERE conditions.
     * - Expression object: Complex query conditions using the Expression builder.
     * - Null: No filtering, retrieves all records.
     *
     * @template T of EntityBase
     *
     * @param class-string<T> $entityClass Fully-qualified class name of the entity (e.g. User::class).
     * @param Expression|int|string|array|null $criteria Optional filtering conditions.
     * @param array $options Optional query options:
     *                       - "joins" => ["relation1", "relation2"]
     *                       - "orderBy" => ["column" => "ASC"]
     *                       - "limit" => int
     *                       - "offset" => int
     *                       - "distinct" => bool
     *
     * @return Collection<T> A collection of hydrated entities.
     *
     * @throws ReflectionException If metadata or reflection fails.
     * @throws DateMalformedStringException If date/time conversion fails during hydration.
     *
     * @example // Fetch all users
     * $users = $entityManager->findAll(User::class);
     *
     * @example // Fetch users with status "active"
     * $users = $entityManager->findAll(User::class, ["status" => "active"]);
     *
     * @example // Fetch users with complex criteria and eager load profile
     * $criteria = Expression::and()
     *     ->andEq("user.status", "active")
     *     ->orLike("user.email", "%@example.com");
     *
     * $users = $entityManager->findAll(
     *     User::class,
     *     $criteria,
     *     ["joins" => ["profile"], "orderBy" => ["user.id" => "DESC"], "limit" => 10]
     * );
     */
    public function findAll(
        string $entityClass,
        Expression|int|string|array|null $criteria = null,
        array $options = []
    ): Collection
    {
        $metadata = $this->getMetadata($entityClass);
        $rows = $this
            ->buildSelectQuery($metadata, $criteria, $options)
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
     * - Associative array criteria (e.g. ["email" => "foo@bar.com"])
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
    public function findBy(
        string $entityName,
        Expression|int|string|array|null $criteria = null,
        array $options = []
    ): ?EntityBase
    {
        $metadata = $this->getMetadata($entityName);
        $rows = $this
            ->buildSelectQuery($metadata, $criteria, $options)
            ->execute()
            ->fetchAll();

        if (!$rows) {
            return null;
        }

        $entity = $this->hydrateEntity($metadata, array_shift($rows));

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
    public function streamAll(
        string $entityName,
        Expression|int|string|array|null $criteria = null,
        array $options = []
    ): Generator
    {
        return $this->streamInternal($entityName, $criteria, $options);
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
     *        - Array: treated as key-value conditions (e.g. ["type" => "admin"]).
     *        - Expression: for advanced query logic (AND/OR groups etc.).
     * @param array $options Optional query options (e.g. joins, ordering, limits).
     *
     * @return Generator<EntityBase> A generator yielding hydrated entity instances.
     *
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public function streamBy(
        string $entityName,
        Expression|int|string|array|null $criteria = null,
        array $options = []
    ): Generator
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
     *        - Array: treated as key-value conditions (e.g. ["status" => "active"]).
     *        - Expression: allows advanced expressions via ExpressionBuilder.
     * @param array $options Optional additional options (e.g. joins, groupBy).
     *
     * @return int The number of rows matching the query.
     *
     * @throws ReflectionException If metadata resolution fails.
     */
    public function countBy(
        string $entityName,
        Expression|int|string|array|null $criteria = null,
        array $options = []
    ): int
    {
        $metadata = $this->getMetadata($entityName);
        $column = "{$metadata->getAlias()}.{$metadata->getPrimaryKey()}";

        $queryBuilder = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select(["COUNT($column)" => "count"])
            ->table($metadata->getTable(), $metadata->getAlias());

        if ($criteria !== null) {
            $queryBuilder->where($this->normalizeCriteria($criteria, $metadata));
        }

        $this->applyOptions($queryBuilder, $options);

        $result = $queryBuilder->execute()->fetch();

        return (int) ($result["count"] ?? 0);
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
     * Accepts primary key scalar (e.g. ID), associative arrays (e.g. ["email" => "..."]),
     * or Expression objects for advanced conditions. This ensures that `findBy()`, `findAll()`, etc.
     * can be called flexibly while still being resolved to a valid internal query structure.
     *
     * @param Expression|int|string|array|null $criteria The raw criteria input from the user.
     * @param MetadataEntity $metadata Metadata used to determine the primary key for scalar lookups.
     *
     * @return Expression|array The normalized criteria ready for query building.
     */
    private function normalizeCriteria(Expression|int|string|array|null $criteria, MetadataEntity $metadata): ?Expression
    {
        if ($criteria instanceof Expression) {
            return $criteria;
        }

        if (is_null($criteria)) {
            return null;
        }

        if (is_scalar($criteria)) {
            return Expression::eq(
                $metadata->getAlias() . "." . $metadata->getPrimaryKey(),
                $criteria
            );
        }

        if (is_array($criteria)) {
            $expr = Expression::and();
            foreach ($criteria as $col => $value) {
                $expr->andEq($metadata->getAlias() . "." . $col, $value);
            }
            return $expr;
        }

        return [$metadata->getPrimaryKey() => $criteria];
    }

    /**
     * Handles one or multiple entities using the provided callback.
     *
     * @param EntityBase|array<EntityBase> $entity The entity or list of entities to handle.
     * @param callable $callback The callback to execute for each entity.
     *
     * @return $this Fluent interface for method chaining.
     *
     * @throws InvalidArgumentException If array contains non-EntityBase elements.
     */
    private function handleEntities(EntityBase|array $entity, callable $callback): self
    {
        if (is_array($entity)) {
            foreach ($entity as $value) {
                if (!($value instanceof EntityBase)) {
                    throw new InvalidArgumentException("Expected instance of EntityBase.");
                }
                $callback($value);
            }
            return $this;
        }

        if (!($entity instanceof EntityBase)) {
            throw new InvalidArgumentException("Expected instance of EntityBase.");
        }

        $callback($entity);
        return $this;
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
     * @throws DateMalformedStringException
     */
    private function streamInternal(
        string $entityName,
        Expression|int|string|array|null $criteria = null,
        array $options = [],
    ): Generator
    {
        $metadata = $this->getMetadata($entityName);
        $primaryKeyColumn = "{$metadata->getAlias()}_{$metadata->getPrimaryKey()}";

        $queryBuilder = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select([])
            ->table($metadata->getTable(), $metadata->getAlias());

        $this->applyJoinsAndColumns($queryBuilder, $metadata, $options);

        if ($criteria !== null) {
            $queryBuilder->where($this->normalizeCriteria($criteria, $metadata));
        }

        $this->applyOptions($queryBuilder, $options);
        yield from $this->groupAndHydrateEntities($queryBuilder->execute(), $primaryKeyColumn, $metadata);
    }

    /**
     * @throws DateMalformedStringException
     * @throws ReflectionException
     */
    private function groupAndHydrateEntities(Statement $statement, string $primaryKeyColumn, MetadataEntity $metadata): Generator
    {
        $currentId = null;
        $group = [];

        while ($row = $statement->fetch()) {
            $id = $row[$primaryKeyColumn];

            if ($currentId !== null && $id !== $currentId) {
                yield $this->hydrateGroupedEntity($group, $metadata);
                $group = [];
            }

            $currentId = $id;
            $group[] = $row;
        }

        if (!empty($group)) {
            yield $this->hydrateGroupedEntity($group, $metadata);
        }
    }

    /**
     * @throws DateMalformedStringException
     * @throws ReflectionException
     */
    private function hydrateGroupedEntity(array $group, MetadataEntity $metadata): EntityBase
    {
        $entity = $this->hydrateEntity($metadata, array_shift($group));

        foreach ($group as $extraRow) {
            $this->hydrator->hydrateRelations($entity, $metadata, $extraRow);
        }

        return $entity;
    }

    private function applyOptions(QueryBuilder $queryBuilder, array $options): void
    {
        if (isset($options["distinct"])) {
            $queryBuilder->distinct();
        }

        if (isset($options["orderBy"])) {
            $queryBuilder->orderBy($options["orderBy"]);
        }

        if (isset($options["limit"])) {
            $queryBuilder->limit($options["limit"]);
        }

        if (isset($options["offset"])) {
            $queryBuilder->offset($options["offset"]);
        }
    }

    private function applyJoinsAndColumns(QueryBuilder $queryBuilder, MetadataEntity $metadata, array $options): void
    {
        $this->applySelectColumns($queryBuilder, $metadata, $metadata->getAlias());

        foreach ($options["joins"] ?? [] as $relationName) {
            $relationMetadata = $metadata->getRelations()[$relationName] ?? null;
            if ($relationMetadata === null) {
                continue;
            }

            $relation = $relationMetadata["relation"];

            if ($relation->fetch === FetchType::Lazy) {
                continue;
            }

            $relationAlias = $metadata->getRelationAlias($relationName);
            $targetMetadata = $this->getMetadata($relation->entity);

            if ($relation instanceof OneToOne || $relation instanceof ManyToOne) {
                $joinColumn = $relationMetadata["joinColumn"] ?? null;
                if ($joinColumn === null) {
                    continue;
                }

                $this->applyJoin(
                    $queryBuilder,
                    $metadata->getAlias(),
                    $relationAlias,
                    $joinColumn->name,
                    $joinColumn->referencedColumn,
                    $targetMetadata->getTable()
                );

                $this->applySelectColumns($queryBuilder, $targetMetadata, $relationAlias);
            }

            if ($relation instanceof OneToMany) {
                $mappedBy = $relation->mappedBy;
                $fkColumn = $targetMetadata->getColumns()["{$mappedBy}_{$targetMetadata->getPrimaryKey()}"]["name"] ?? null;

                if ($fkColumn === null) {
                    continue;
                }

                $this->applyJoin(
                    $queryBuilder,
                    $metadata->getAlias(),
                    $relationAlias,
                    $metadata->getPrimaryKey(),
                    $fkColumn,
                    $targetMetadata->getTable()
                );

                $this->applySelectColumns($queryBuilder, $targetMetadata, $relationAlias);
            }
        }
    }

    private function applySelectColumns(QueryBuilder $queryBuilder, MetadataEntity $metadata, string $alias): void
    {
        foreach ($metadata->getColumns() as $column) {
            $queryBuilder->select([
                "{$alias}.{$column["name"]}" => "{$alias}_{$column["name"]}"
            ]);
        }
    }

    private function applyJoin(QueryBuilder $queryBuilder, string $sourceAlias, string $targetAlias, string $sourceColumn, string $targetColumn, string $targetTable): void
    {
        $queryBuilder->leftJoin(
            $targetTable,
            $targetAlias,
            sprintf(
                "%s.%s = %s.%s",
                $sourceAlias,
                $sourceColumn,
                $targetAlias,
                $targetColumn
            )
        );
    }



    /**
     * @throws ReflectionException
     */
    private function buildSelectQuery(
        MetadataEntity $metadata,
        Expression|int|string|array|null $criteria,
        array $options = []
    ): QueryBuilder {
        $queryBuilder = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select([])
            ->table($metadata->getTable(), $metadata->getAlias());

        $this->applyJoinsAndColumns($queryBuilder, $metadata, $options);

        if ($criteria !== null) {
            $queryBuilder->where($this->normalizeCriteria($criteria, $metadata));
        }

        $this->applyOptions($queryBuilder, $options);

        return $queryBuilder;
    }

}
