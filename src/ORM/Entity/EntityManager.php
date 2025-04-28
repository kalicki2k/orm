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
     * Retrieves a single entity instance by primary key, simple criteria, or complex expressions.
     *
     * This method executes a SELECT query to fetch the first matching entity based on:
     * - Scalar value: Treated as primary key lookup (e.g. findBy(User::class, 1)).
     * - Associative array: Simple WHERE conditions (e.g. ['email' => 'foo@bar.com']).
     * - Expression object: For advanced AND/OR logic using the Expression builder.
     * - Null: Returns the first entity found (use with caution).
     *
     * If no matching record is found, the method returns `null`.
     *
     * For EAGER-loaded relations (e.g. OneToMany), additional rows are grouped and hydrated properly.
     *
     * @param class-string<EntityBase> $entityName Fully-qualified entity class (e.g. User::class).
     * @param Expression|int|string|array|null $criteria Optional filtering:
     *        - Primary key (int|string)
     *        - Associative array conditions
     *        - Expression object
     *        - Null for no filtering
     * @param array $options Query options like:
     *        - "joins" => array of relations to eager load
     *        - "orderBy" => ["column" => "ASC|DESC"]
     *        - "limit" => int
     *        - "offset" => int
     *
     * @return EntityBase|null The hydrated entity or null if no result.
     *
     * @throws DateMalformedStringException If a date/time format is invalid during hydration.
     * @throws ReflectionException If metadata or reflection fails.
     *
     * @example // Find user by primary key
     * $user = $entityManager->findBy(User::class, 5);
     *
     * @example // Find user by email
     * $user = $entityManager->findBy(User::class, ["email" => "john@example.com"]);
     *
     * @example // Find active user with complex criteria and eager load profile
     * $criteria = Expression::and()
     *     ->andEq("user.status", "active")
     *     ->orLike("user.email", "%@example.com");
     *
     * $user = $entityManager->findBy(
     *     User::class,
     *     $criteria,
     *     ["joins" => ["profile"]]
     * );
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
     * Streams all entities of a given type using a generator for efficient memory usage.
     *
     * This method is ideal for processing large datasets, as it fetches and hydrates entities
     * one by one without loading the entire result set into memory.
     *
     * Supports optional filtering, sorting, pagination, and eager loading of relations.
     * Useful for batch processing, exports, or background jobs where performance matters.
     *
     * @param class-string<EntityBase> $entityName Fully-qualified entity class name (e.g. User::class).
     * @param Expression|int|string|array|null $criteria Optional filtering conditions:
     *        - Scalar (int|string): Lookup by primary key.
     *        - Array: Simple WHERE conditions.
     *        - Expression: Complex query logic via Expression builder.
     *        - Null: Stream all records.
     * @param array $options Query options:
     *        - "joins"   => Relations to eager load (e.g. ["profile", "posts"])
     *        - "orderBy" => ["column" => "ASC|DESC"]
     *        - "limit"   => Max number of records
     *        - "offset"  => Starting point for pagination
     *
     * @return Generator<EntityBase> Yields hydrated entity instances one at a time.
     *
     * @throws ReflectionException If metadata or reflection fails.
     * @throws DateMalformedStringException If date/time conversion fails during hydration.
     *
     * @example // Stream all users
     * foreach ($entityManager->streamAll(User::class) as $user) {
     *     echo $user->getUsername();
     * }
     *
     * @example // Stream active users with eager-loaded profiles
     * $criteria = Expression::eq("user.status", "active");
     * foreach ($entityManager->streamAll(User::class, $criteria, ["joins" => ["profile"]]) as $user) {
     *     echo $user->getProfile()->getBio();
     * }
     *
     * @example // Stream users in batches of 100, ordered by ID
     * foreach ($entityManager->streamAll(User::class, null, ["orderBy" => ["user.id" => "ASC"], "limit" => 100]) as $user) {
     *     // Process user
     * }
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
     * Streams entities matching the given criteria using a memory-efficient generator.
     *
     * This method is perfect for handling large datasets where you need to filter specific records
     * without loading the entire result set into memory. Entities are fetched, hydrated, and yielded
     * one by one, making it ideal for batch processing, data exports, or background tasks.
     *
     * Supports flexible filtering via:
     * - Scalar: Lookup by primary key.
     * - Array: Simple key-value WHERE conditions.
     * - Expression: Complex queries using the Expression builder.
     * - Null: Streams all records without filtering.
     *
     * You can also define eager loading of relations, sorting, pagination, and distinct queries through options.
     *
     * @param class-string<EntityBase> $entityName Fully-qualified class name of the entity (e.g. User::class).
     * @param Expression|int|string|array|null $criteria Filtering conditions to apply:
     *        - Scalar (int|string): Lookup by primary key.
     *        - Array: ["status" => "active"]
     *        - Expression: Complex WHERE clauses.
     *        - Null: No filtering.
     * @param array $options Additional query options:
     *        - "joins"   => ["relation1", "relation2"] // Eager load relations
     *        - "orderBy" => ["column" => "ASC|DESC"]
     *        - "limit"   => int
     *        - "offset"  => int
     *        - "distinct" => bool
     *
     * @return Generator<EntityBase> Yields each hydrated entity instance individually.
     *
     * @throws ReflectionException If metadata parsing or reflection fails.
     * @throws DateMalformedStringException If date/time fields cannot be parsed correctly.
     *
     * @example // Stream a user by primary key
     * foreach ($entityManager->streamBy(User::class, 5) as $user) {
     *     echo $user->getEmail();
     * }
     *
     * @example // Stream users with status 'active'
     * foreach ($entityManager->streamBy(User::class, ["status" => "active"]) as $user) {
     *     echo $user->getUsername();
     * }
     *
     * @example // Stream users with complex condition and eager-loaded posts
     * $criteria = Expression::and()
     *     ->andEq("user.status", "active")
     *     ->andGte("user.id", 100);
     *
     * foreach ($entityManager->streamBy(User::class, $criteria, ["joins" => ["posts"], "limit" => 50]) as $user) {
     *     echo count($user->getPosts());
     * }
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
     * Counts the number of entities matching the given criteria.
     *
     * This method performs a `SELECT COUNT(...)` query on the entity's table, applying optional filters,
     * joins, and other SQL options. It is ideal for quickly determining the number of records
     * without retrieving full entity data.
     *
     * Supported criteria formats:
     * - Scalar (int|string): Lookup by primary key.
     * - Array: Simple key-value WHERE conditions (e.g. ["status" => "active"]).
     * - Expression: Complex conditions using the Expression builder.
     * - Null: Counts all records in the table.
     *
     * Supports additional query customization via options like joins (for EAGER relations), grouping,
     * ordering (where applicable), limits, and distinct counts.
     *
     * ⚠️ Note: Using joins can impact the count result depending on SQL behavior (e.g. row multiplication).
     * Use `distinct` or `groupBy` wisely when counting with joins.
     *
     * @param class-string<EntityBase> $entityName Fully-qualified class name of the entity (e.g. User::class).
     * @param Expression|int|string|array|null $criteria Optional filtering:
     *        - Scalar: Primary key lookup.
     *        - Array: Simple conditions.
     *        - Expression: Advanced logic.
     *        - Null: No filtering.
     * @param array $options Additional options:
     *        - "joins"    => ["relation1", "relation2"]  // Eager loading (may affect count)
     *        - "groupBy"  => ["column1", "column2"]
     *        - "distinct" => true
     *
     * @return int The total number of matching records.
     *
     * @throws ReflectionException If entity metadata parsing fails.
     *
     * @example // Count all users
     * $totalUsers = $entityManager->countBy(User::class);
     *
     * @example // Count users with status 'active'
     * $activeUsers = $entityManager->countBy(User::class, ["status" => "active"]);
     *
     * @example // Count users with complex criteria
     * $criteria = Expression::and()
     *     ->andEq("user.status", "active")
     *     ->orLike("user.email", "%@example.com");
     *
     * $count = $entityManager->countBy(User::class, $criteria);
     *
     * @example // Count distinct users with posts (be careful with joins)
     * $count = $entityManager->countBy(
     *     User::class,
     *     null,
     *     ["joins" => ["posts"], "distinct" => true]
     * );
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
     * Commits all pending entity changes to the database.
     *
     * The `flush()` method synchronizes the in-memory state of managed entities with the database.
     * It processes all scheduled operations (insert, update, delete) in the correct order, ensuring
     * data integrity, respecting cascading rules, and handling dependencies between entities.
     *
     * **Key Features:**
     * - Executes batched **INSERT**, **UPDATE**, and **DELETE** statements.
     * - Respects **CascadeType** rules (e.g. Persist, Remove).
     * - Orders operations to satisfy foreign key constraints (e.g. parents before children on insert, reverse on delete).
     * - Clears the UnitOfWork after successful execution.
     *
     * Use `flush()` after calling `persist()`, `update()`, or `delete()` to apply changes.
     * Multiple entity operations are grouped for optimal performance.
     *
     * ⚠️ **Note:**
     * - `flush()` only affects entities tracked by the **UnitOfWork**.
     * - Changes outside of ORM control (e.g. raw SQL) are not considered.
     * - It's recommended to batch multiple operations before flushing to reduce database load.
     *
     * @throws ReflectionException If reflection or metadata resolution fails during processing.
     *
     * @example // Persist a new user
     * $user = new User();
     * $user->setUsername("john_doe")->setEmail("john@example.com");
     *
     * $entityManager->persist($user);
     * $entityManager->flush();
     *
     * @example // Update and delete in a single flush
     * $user->setEmail("new@example.com");
     * $entityManager->update($user);
     * $entityManager->delete($oldUser);
     * $entityManager->flush();
     *
     * @example // Batch insert
     * foreach ($users as $user) {
     *     $entityManager->persist($user);
     * }
     * $entityManager->flush();
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
     * Normalizes different criteria formats into a unified Expression for query building.
     *
     * This method accepts flexible input formats for filtering conditions and converts them
     * into a standardized {@see Expression} object, which can be safely used in WHERE clauses.
     *
     * Supported input types:
     * - **null**: No WHERE clause will be applied.
     * - **Scalar (int|string)**: Treated as a primary key lookup (e.g., `alias.id = :id`).
     * - **Associative array**: Each key-value pair becomes an AND condition (e.g., `status = :status`).
     * - **Expression**: Returns the given Expression as-is for advanced conditions.
     *
     * All column names are automatically prefixed with the entity alias to ensure correct SQL generation,
     * especially when dealing with joins.
     *
     * @param Expression|int|string|array|null $criteria The user-defined filtering conditions.
     * @param MetadataEntity $metadata Entity metadata providing alias and primary key context.
     *
     * @return Expression|null The normalized Expression for WHERE clauses, or null if no criteria is provided.
     */
    private function normalizeCriteria(Expression|int|string|array|null $criteria, MetadataEntity $metadata): ?Expression
    {
        if ($criteria instanceof Expression) {
            return $criteria;
        }

        if (is_null($criteria)) {
            return null;
        }

        if (is_array($criteria)) {
            $expr = Expression::and();
            foreach ($criteria as $col => $value) {
                $expr->andEq($metadata->getAlias() . "." . $col, $value);
            }
            return $expr;
        }

        return Expression::eq(
            $metadata->getAlias() . "." . $metadata->getPrimaryKey(),
            $criteria
        );
    }

    /**
     * Applies a callback to one or multiple entities in a safe and consistent manner.
     *
     * This utility method ensures that the provided input is either a single {@see EntityBase}
     * instance or an array of such instances. It then executes the given callback for each entity.
     *
     * Useful for internal operations like scheduling entities for persistence, update, or deletion
     * within the {@see UnitOfWork}, while enforcing strict type safety.
     *
     * @param EntityBase|array<EntityBase> $entity Single entity or an array of entities to process.
     * @param callable(EntityBase): void $callback The callback to apply to each entity.
     *
     * @return $this Fluent interface to allow method chaining.
     *
     * @throws InvalidArgumentException If the input is not an EntityBase instance or an array containing only EntityBase instances.
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

    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->databaseDriver, $this->logger);
    }


    /**
     * Executes a streaming query to fetch entities matching the given criteria and options.
     *
     * This internal method builds a dynamic SELECT query based on entity metadata, applies
     * filtering conditions, joins, and other SQL options, and streams the result set using a generator.
     *
     * Designed for memory-efficient processing of large datasets by yielding entities one at a time.
     * It handles grouping of rows for proper hydration when joins are involved and avoids duplication
     * through identity mapping.
     *
     * @template T of EntityBase
     *
     * @param class-string<T> $entityName  Fully-qualified class name of the target entity.
     * @param Expression|int|string|array|null $criteria  Filtering conditions for the WHERE clause.
     *        Supports primary key lookup, associative arrays, or complex expressions.
     * @param array $options  Additional query options such as:
     *                        - "joins": array of relations to eager load
     *                        - "orderBy": sorting instructions
     *                        - "limit": maximum number of results
     *                        - "offset": starting point for pagination
     *                        - "distinct": boolean flag for DISTINCT queries
     *
     * @return Generator<T> A generator yielding hydrated entity instances sequentially.
     *
     * @throws ReflectionException If metadata parsing or reflection fails.
     * @throws DateMalformedStringException If date/time fields cannot be parsed during hydration.
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
     * Groups SQL result rows by primary key and hydrates entities accordingly.
     *
     * This method processes a flat SQL result set (including possible JOINs) and groups
     * rows that belong to the same entity based on the primary key column.
     * For each group, it triggers the hydration process to reconstruct fully populated entity instances,
     * including eager-loaded relations.
     *
     * Returns a generator to yield each hydrated entity, ensuring efficient memory usage
     * even with large datasets.
     *
     * @param Statement $statement The executed database statement providing fetched rows.
     * @param string $primaryKeyColumn The alias of the primary key column used for grouping.
     * @param MetadataEntity $metadata Metadata describing the structure of the entity.
     *
     * @return Generator<EntityBase> A generator yielding hydrated entities one by one.
     *
     * @throws DateMalformedStringException If date/time fields in the data cannot be parsed correctly.
     * @throws ReflectionException If reflection operations fail during hydration.
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
     * Hydrates a single entity instance from a grouped set of SQL result rows.
     *
     * This method takes a group of rows that represent one entity (including its eager-loaded relations),
     * hydrates the base entity from the first row, and processes remaining rows to populate
     * collection-based or repeated relation data (e.g. OneToMany).
     *
     * It ensures that even in JOIN-heavy queries, where an entity might appear multiple times
     * due to related records, only one consistent entity instance is returned with all relations hydrated.
     *
     * @param array $group The grouped SQL rows belonging to a single entity instance.
     * @param MetadataEntity $metadata Metadata describing the entity's structure and relations.
     *
     * @return EntityBase The fully hydrated entity with its relations.
     *
     * @throws DateMalformedStringException If date or datetime fields contain invalid formats during hydration.
     * @throws ReflectionException If reflection fails while setting entity properties.
     */
    private function hydrateGroupedEntity(array $group, MetadataEntity $metadata): EntityBase
    {
        $entity = $this->hydrateEntity($metadata, array_shift($group));

        foreach ($group as $extraRow) {
            $this->hydrator->hydrateRelations($entity, $metadata, $extraRow);
        }

        return $entity;
    }

    /**
     * Applies common SQL query options to the QueryBuilder instance.
     *
     * This method processes optional query modifiers such as DISTINCT, ORDER BY, LIMIT, and OFFSET
     * from the provided options array and applies them to the given QueryBuilder.
     * It standardizes how these options are handled across all query-building operations.
     *
     * Supported options:
     * - **distinct**: (bool) Adds DISTINCT to the SELECT clause.
     * - **orderBy**: (string|array) Defines sorting order. Accepts column or [column => direction].
     * - **limit**: (int) Restricts the number of returned rows.
     * - **offset**: (int) Skips a number of rows before starting to return results.
     *
     * @param QueryBuilder $queryBuilder The query builder instance to apply options to.
     * @param array $options The associative array of query options.
     *
     * @return void
     */
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

    /**
     * Dynamically applies SELECT columns and JOIN clauses based on entity metadata and query options.
     *
     * This method ensures that all necessary columns from the base entity and its relations
     * are included in the SELECT statement. It also constructs the appropriate SQL JOINs
     * for relations marked with `FetchType::Eager`. Relations configured as `FetchType::Lazy`
     * are ignored to optimize query performance.
     *
     * Features:
     * - Adds aliased columns for the base entity.
     * - Processes relations from the "joins" option:
     *    - Supports OneToOne, ManyToOne, and OneToMany relationships.
     *    - Automatically resolves join conditions based on metadata (JoinColumn, mappedBy, etc.).
     *    - Skips joins for relations using Lazy loading.
     * - Delegates JOIN and column handling to helper methods (`applyJoin`, `applySelectColumns`).
     *
     * @param QueryBuilder $queryBuilder The query builder instance to modify.
     * @param MetadataEntity $metadata Metadata of the root entity being queried.
     * @param array $options Query options, specifically:
     *                       - "joins" => array of relation names to include.
     *
     * @return void
     *
     * @throws ReflectionException If relation metadata resolution fails.
     */
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

    /**
     * Appends all columns of the given entity to the SELECT clause with proper aliasing.
     *
     * This method iterates over the entity's column definitions from metadata and adds them
     * to the QueryBuilder's SELECT statement. Each column is prefixed with the provided table alias
     * and assigned a unique SQL alias in the format: `alias_columnName`.
     *
     * This ensures:
     * - Clear disambiguation of columns when dealing with JOINs.
     * - Consistent mapping for hydration by using predictable aliases.
     *
     * Example output:
     * SELECT user.id AS user_id, user.name AS user_name ...
     *
     * @param QueryBuilder $queryBuilder The QueryBuilder instance being constructed.
     * @param MetadataEntity $metadata Metadata containing column definitions.
     * @param string $alias The table alias to prefix each column with.
     *
     * @return void
     */
    private function applySelectColumns(QueryBuilder $queryBuilder, MetadataEntity $metadata, string $alias): void
    {
        foreach ($metadata->getColumns() as $column) {
            $queryBuilder->select([
                "{$alias}.{$column["name"]}" => "{$alias}_{$column["name"]}"
            ]);
        }
    }

    /**
     * Adds a LEFT JOIN clause to the QueryBuilder for the specified relation.
     *
     * This helper method constructs a standardized SQL LEFT JOIN between a source table alias
     * and a target table, based on the provided join columns. It abstracts the repetitive join logic,
     * ensuring consistent formatting across all relation-based queries.
     *
     * The ON condition is generated as:
     *   `sourceAlias.sourceColumn = targetAlias.targetColumn`
     *
     * Example output:
     *   LEFT JOIN `profiles` AS `user__profile` ON user.profile_id = user__profile.id
     *
     * @param QueryBuilder $queryBuilder The QueryBuilder instance to append the JOIN to.
     * @param string $sourceAlias Alias of the source (owning) entity table.
     * @param string $targetAlias Alias for the target (related) entity table.
     * @param string $sourceColumn The column in the source table used for joining.
     * @param string $targetColumn The referenced column in the target table.
     * @param string $targetTable  The physical name of the target database table.
     *
     * @return void
     */
    private function applyJoin(
        QueryBuilder $queryBuilder,
        string $sourceAlias,
        string $targetAlias,
        string $sourceColumn,
        string $targetColumn,
        string $targetTable
    ): void
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
     * Builds a dynamic SELECT query based on entity metadata, criteria, and options.
     *
     * This method prepares a fully configured QueryBuilder instance for SELECT operations.
     * It automatically applies table aliases, selects all relevant columns, handles JOINs
     * for eager-loaded relations, applies WHERE conditions, and respects additional options
     * like ordering, limits, offsets, and distinct selections.
     *
     * Primär genutzt von Methoden wie `findAll()`, `findBy()`, `streamAll()` und `streamBy()`
     * zur einheitlichen Generierung von SELECT-Statements.
     *
     * @param MetadataEntity $metadata The metadata of the root entity defining table, columns, and relations.
     * @param Expression|int|string|array|null $criteria Optional filtering conditions for the WHERE clause.
     * @param array $options Additional query options:
     *                       - "joins" => array of relations to eagerly load
     *                       - "orderBy" => array specifying sort order
     *                       - "limit" => int to restrict number of results
     *                       - "offset" => int for pagination
     *                       - "distinct" => bool to enforce DISTINCT selection
     *
     * @return QueryBuilder A fully prepared QueryBuilder instance ready for execution.
     *
     * @throws ReflectionException If relation metadata resolution fails during JOIN construction.
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
