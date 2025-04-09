<?php

namespace ORM;

use Generator;
use InvalidArgumentException;
use ORM\Drivers\DatabaseDriver;
use ORM\Drivers\Statement;
use ORM\Logger\LogHelper;
use Psr\Log\LoggerInterface;
use ReflectionException;
use RuntimeException;

/**
 * Class EntityManager
 *
 * Central access point for interacting with the ORM.
 * Manages entity lifecycle (persist, update, delete, find) and coordinates
 * with UnitOfWork to batch and commit database operations.
 *
 * @see UnitOfWork
 */
class EntityManager_old
{
    /**
     * Handles batching and execution of persistence operations.
     *
     * @var UnitOfWork
     */
    private UnitOfWork $unitOfWork;

    /**
     * EntityManager constructor.
     *
     * @param DatabaseDriver $databaseDriver The database driver implementation (e.g., PDO-based).
     * @param LoggerInterface|null $logger Optional PSR-3 logger for SQL and debug output.
     */
    public function __construct(
        private readonly DatabaseDriver   $databaseDriver,
        private readonly ?LoggerInterface $logger = null
    ) {
        $this->unitOfWork = new UnitOfWork($this);
    }

    /**
     * Returns the currently configured database driver instance.
     *
     * This driver is responsible for low-level database operations such as
     * preparing SQL statements, quoting identifiers, executing queries, and more.
     *
     * Example usage:
     *   $driver = $entityManager->getDatabaseDriver();
     *   $statement = $driver->prepare("SELECT * FROM users");
     *
     * @return DatabaseDriver The active database driver used by the EntityManager.
     *
     * @example
     * $driver = $entityManager->getDatabaseDriver();
     * $statement = $driver->prepare("SELECT * FROM users");
     *
     * @see ORM\Drivers\DatabaseDriver
     */
    public function getDatabaseDriver(): DatabaseDriver
    {
        return $this->databaseDriver;
    }

    /**
     * Returns the PSR-3 compatible logger instance, if configured.
     *
     * The logger can be used for tracing and debugging SQL operations, internal events,
     * or ORM-specific actions. If no logger is configured, this method returns `null`.
     *
     * @return LoggerInterface|null The logger used for SQL and ORM logging, or null if none set.
     *
     * @example
     * $logger = $entityManager->getLogger();
     * $logger?->info("Something happened");
     *
     * @see Psr\Log\LoggerInterface
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Retrieves metadata (table name and column definitions) for a given entity.
     *
     * This method delegates to the MetadataParser to extract ORM-related information
     * using PHP attributes like #[Table] and #[Column] from the provided entity.
     * It supports both class names and instantiated objects.
     *
     * @param string|object $entity Either a fully qualified class name (e.g. Entity\User::class)
     *                              or an instantiated entity object.
     *
     * @return array An array containing two elements:
     *               - string $table: the database table name
     *               - array  $columns: associative array of column metadata per property
     *
     * @throws ReflectionException If the class or its attributes cannot be reflected
     *
     * @example
     * [$table, $columns] = $entityManager->getMetadata(User::class);
     * echo "Table name: $table";
     * echo "Columns: " . json_encode($columns);
     */
    public function getMetadata(string|object $entity): array
    {
        $object = is_object($entity) ? $entity : new $entity();
        return MetadataParser::parse($object);
    }

    /**
     * Retrieves the name of the primary key column from a given list of columns.
     *
     * This method is used internally to determine which column is the primary key
     * in an entity's column metadata. It assumes only one primary key exists.
     *
     * @param array $columns An associative array of column metadata (from MetadataParser).
     *
     * @return string|null The name of the primary key column if found, otherwise null.
     *
     * @example
     * $pk = $entityManager->getPrimaryKeyColumn($columns);
     * if ($pk !== null) {
     *     echo "Primary key is: {$pk}";
     * }
     */
    public function getPrimaryKeyColumn(array $columns): ?string
    {
        foreach ($columns as $col) {
            if (!empty($col['primary'])) {
                return $col['column'];
            }
        }
        return null;
    }

    /**
     * Marks an entity or array of entities for insertion into the database.
     *
     * This method schedules the provided entity or list of entities for insertion by adding
     * them to the UnitOfWork's internal list of new entities. These will be inserted into
     * the database when `flush()` is called.
     *
     * If an array is passed, all contained items must be valid objects, otherwise an
     * InvalidArgumentException will be thrown.
     *
     * @param object|array $entity A single entity instance or an array of entity instances
     *
     * @throws InvalidArgumentException If the input is not an object or an array of objects
     *
     * @example
     * $user = new User();
     * $user->username = 'alice';
     * $entityManager->persist($user);
     *
     * @example
     * $users = [$user1, $user2];
     * $entityManager->persist($users);
     */
    public function persist(object|array $entity): void
    {
        if (is_array($entity)) {
            foreach ($entity as $value) {
                if (!is_object($value)) {
                    throw new InvalidArgumentException("persist() expects an object or array of objects.");
                }
                $this->persist($value);
            }
            return;
        }

        $this->unitOfWork->scheduleInsert($entity);
    }

    /**
     * Marks an entity or array of entities for update.
     *
     * This method schedules one or more existing entities to be updated in the database
     * during the next `flush()` operation. It adds the entities to the UnitOfWork's
     * update queue.
     *
     * If an array is passed, all contained items must be valid objects. If a non-object
     * is encountered, an InvalidArgumentException will be thrown.
     *
     * @param object|array $entity A single entity or an array of entities to be updated
     *
     * @throws InvalidArgumentException If the input is not an object or an array of objects
     *
     * @example
     * $user = $entityManager->find(User::class, 1);
     * $user->email = 'updated@example.com';
     * $entityManager->update($user);
     *
     * @example
     * $users = [$user1, $user2];
     * $entityManager->update($users);
     *
     * @see EntityManager::flush() to execute pending operations
     */
    public function update(object|array $entity): void
    {
        if (is_array($entity)) {
            foreach ($entity as $value) {
                if (!is_object($value)) {
                    throw new InvalidArgumentException("update() expects an object or array of objects.");
                }
                $this->update($value);
            }
            return;
        }

        $this->unitOfWork->scheduleUpdate($entity);
    }

    /**
     * Marks an entity or array of entities for deletion.
     *
     * This method schedules one or more entities for removal from the database.
     * The actual DELETE SQL is executed during the next `flush()` operation.
     *
     * If an array is passed, all elements must be objects. If a non-object is encountered,
     * an InvalidArgumentException is thrown.
     *
     * @param object|array $entity A single entity or an array of entities to be deleted
     *
     * @throws InvalidArgumentException If the argument is not an object or an array of objects
     *
     * @example
     * // Delete a single user
     * $user = $entityManager->find(User::class, 5);
     * $entityManager->delete($user);
     *
     * @example
     * // Delete multiple users
     * $users = [$user1, $user2];
     * $entityManager->delete($users);
     *
     * @see EntityManager::flush() to execute pending operations
     */
    public function delete(object|array $entity): void
    {
        if (is_array($entity)) {
            foreach ($entity as $value) {
                if (!is_object($value)) {
                    throw new InvalidArgumentException("delete() expects an object or array of objects.");
                }
                $this->delete($value);
            }
            return;
        }

        $this->unitOfWork->scheduleDelete($entity);
    }

    /**
     * Executes all pending operations (insert, update, delete) in the UnitOfWork.
     *
     * This method processes all scheduled entity changes that were previously marked
     * via `persist()`, `update()`, or `delete()`. It delegates the execution of SQL statements
     * to the `UnitOfWork`, which performs them in a defined order:
     *   1. Deletes
     *   2. Updates
     *   3. Inserts
     *
     * This ensures referential integrity and that the entity graph remains consistent.
     *
     * @example
     * $user = new User();
     * $user->username = 'john';
     * $entityManager->persist($user);
     * $entityManager->flush(); // Executes INSERT
     *
     * @see EntityManager::persist()
     * @see EntityManager::update()
     * @see EntityManager::delete()
     */
    public function flush(): void
    {
        $this->unitOfWork->commit();
    }

    /**
     * Finds and returns a single entity by its primary key.
     *
     * This method supports both single and composite primary keys. It constructs
     * a `SELECT` query using the metadata of the given entity and the provided ID(s),
     * executes it, and returns a hydrated entity object or `null` if not found.
     *
     * @param string $entity The fully-qualified class name of the entity.
     * @param int|string|array $id The primary key value (or associative array for composite keys).
     *
     * @return object|null The hydrated entity instance or null if not found.
     *
     * @throws ReflectionException If metadata parsing fails.
     * @throws InvalidArgumentException If key structure doesn't match entity definition.
     * @throws RuntimeException If no primary key is defined.
     *
     * @example
     *    $user = $entityManager->find(User::class, 1);
     *    $order = $entityManager->find(Order::class, ['order_id' => 42, 'version' => 2]);
     */
    public function find(string $entity, int|string|array $id): ?object
    {
        [$table, $columns] = $this->getMetadata($entity);
        $primaryConditions = [];
        $values = [];

        foreach ($columns as $column) {
            if ($column["primary"]) {
                $columnName = $column["column"];
                $quoted = $this->databaseDriver->quoteIdentifier($columnName);
                $primaryConditions[] = "{$quoted} = :{$columnName}";

                if (is_array($id)) {
                    if (!array_key_exists($columnName, $id)) {
                        throw new InvalidArgumentException("Missing value for composite key part: '{$columnName}'");
                    }
                    $values[":{$columnName}"] = $id[$columnName];
                } else {
                    $values[":{$columnName}"] = $id;
                }
            }
        }

        if (empty($primaryConditions)) {
            throw new RuntimeException("No primary key defined for entity $entity");
        }

        if (count($primaryConditions) > 1 && !is_array($id)) {
            throw new InvalidArgumentException("Composite primary key requires an associative array of values.");
        }

        $sql = sprintf(
            "SELECT %s FROM %s WHERE %s",
            $this->buildSelectFields($columns),
            $this->databaseDriver->quoteIdentifier($table),
            implode(" AND ", $primaryConditions),
        );

        LogHelper::query($sql, $values, $this->getLogger());
        $statement = $this->databaseDriver->prepare($sql);
        $this->bindParameters($statement, $values);
        $statement->execute();
        $data = $statement->fetch();

        return $data ? $this->hydrateEntity($entity, $columns, $data) : null;
    }

    /**
     * Retrieves and returns all records of the specified entity from the database.
     *
     * This method loads all rows for the given entity class, hydrates them into
     * object instances, and returns them as an array. It is suitable for small to
     * medium datasets since all rows are loaded into memory at once.
     *
     * For large datasets, use {@see EntityManager::streamAll()} instead.
     *
     * @param string $entity Fully-qualified class name of the entity.
     *
     * @return array<int, object> Array of hydrated entity instances indexed numerically.
     *
     * @throws ReflectionException If metadata parsing or reflection fails.
     *
     * @example
     * $users = $entityManager->findAll(User::class);
     * foreach ($users as $user) {
     *     echo $user->username;
     * }
     *
     * @see EntityManager::streamAll()
     */
    public function findAll(string $entity): array
    {
        [$table, $columns] = $this->getMetadata($entity);

        $sql = sprintf(
            "SELECT %s FROM %s",
            $this->buildSelectFields($columns),
            $this->databaseDriver->quoteIdentifier($table),
        );

        LogHelper::query($sql, [], $this->getLogger());
        $statement = $this->getDatabaseDriver()->prepare($sql);
        $statement->execute();
        $rows = $statement->fetchAll();
        $entities = [];

        foreach ($rows as $row) {
            $entities[] = $this->hydrateEntity($entity, $columns, $row);
        }

        return $entities;
    }

    /**
     * Finds and returns all entities matching the given criteria.
     *
     * Builds a dynamic SQL `SELECT` query using the provided filters, ordering,
     * and pagination parameters. Returns an array of hydrated entity objects.
     *
     * For large datasets, use {@see EntityManager::streamBy()} instead.
     *
     * @param string $entity Fully-qualified class name of the entity.
     * @param array $criteria Associative array of column => value pairs to filter by (WHERE clause).
     * @param array $orderBy Associative array of column => direction (ASC|DESC). Default is empty.
     * @param int|null $limit Maximum number of records to return. Optional.
     * @param int|null $offset Number of records to skip (for pagination). Optional.
     *
     * @return array<int, object> An array of entity instances matching the criteria.
     *
     * @throws ReflectionException If the entity metadata cannot be parsed.
     * @throws InvalidArgumentException If criteria are missing or malformed.
     *
     * @example
     * $users = $entityManager->findBy(User::class, ['active' => true], ['created_at' => 'DESC'], 10, 0);
     */
    public function findBy(
        string $entity,
        array $criteria,
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        [$table, $columns] = $this->getMetadata($entity);
        [$sql, $values] = $this->buildSelectQuery($table, $columns, $criteria, $orderBy, $limit, $offset);

        LogHelper::query($sql, $values, $this->getLogger());
        $statement = $this->databaseDriver->prepare($sql);
        $this->bindParameters($statement, $values);
        $statement->execute();

        $entities = [];

        foreach ($statement->fetchAll() as $row) {
            $entities[] = $this->hydrateEntity($entity, $columns, $row);
        }

        return $entities;
    }

    /**
     * Finds and returns the first entity matching the given criteria.
     *
     * Useful when only one result is expected, or the first match is sufficient.
     * Internally uses {@see EntityManager::findBy()} with a limit of 1.
     *
     * @param string $entity   Fully-qualified class name of the entity.
     * @param array $criteria  Associative array of column => value pairs to filter by (WHERE clause).
     *
     * @return object|null The matched entity instance, or null if no result is found.
     *
     * @throws ReflectionException If metadata parsing or hydration fails.
     * @throws InvalidArgumentException If criteria is missing or malformed.
     * @throws RuntimeException If no primary key is defined on the entity.
     *
     * @example
     * $user = $entityManager->findOneBy(User::class, ['email' => 'john@example.com']);
     *
     * @see EntityManager::findBy()
     */
    public function findOneBy(string $entity, array $criteria): ?object
    {
        $results = $this->findBy($entity, $criteria, [], 1, 0);
        return $results[0] ?? null;
    }

    /**
     * Streams all records of a given entity as a lazy-loaded Generator.
     *
     * This method is ideal for large datasets, as it yields one entity at a time without
     * loading the entire result set into memory. It uses internal hydration and
     * IdentityMap support to avoid duplicate objects in memory.
     *
     * @param string $entity Fully-qualified class name of the entity.
     *
     * @return Generator<int, object> Yields hydrated entity instances, keyed by position.
     *
     * @throws ReflectionException If metadata parsing or hydration fails.
     *
     * @example
     * foreach ($entityManager->streamAll(User::class) as $user) {
     *     echo $user->username;
     * }
     *
     * @see EntityManager::findAll() For eagerly loading all records into memory.
     */
    public function streamAll(string $entity): Generator
    {
        [$table, $columns] = $this->getMetadata($entity);

        $sql = sprintf(
            "SELECT %s FROM %s",
            $this->buildSelectFields($columns),
            $this->databaseDriver->quoteIdentifier($table),
        );

        LogHelper::query($sql, [], $this->getLogger());
        $statement = $this->getDatabaseDriver()->prepare($sql);
        $statement->execute();

        // Yield one entity at a time using the identity map (efficient hydration)
        while ($row = $statement->fetch()) {
            yield $this->hydrateEntity($entity, $columns, $row);
        }
    }

    /**
     * Streams records matching the given criteria as a lazy-loaded Generator.
     *
     * This method is ideal for processing large result sets efficiently, as it fetches and hydrates
     * one entity at a time instead of loading all records into memory. It supports flexible filtering,
     * ordering, and pagination through SQL WHERE, ORDER BY, LIMIT and OFFSET clauses.
     *
     * @param string $entity Fully-qualified class name of the entity.
     * @param array $criteria Associative array of column => value pairs used for the WHERE clause.
     * @param array $orderBy Optional ordering, e.g., ['name' => 'ASC'].
     * @param int|null $limit Optional LIMIT clause to restrict result size.
     * @param int|null $offset Optional OFFSET clause for pagination.
     *
     * @return Generator<int, object> A generator yielding hydrated entity instances, keyed by index.
     *
     * @throws ReflectionException If entity metadata can't be parsed.
     * @throws InvalidArgumentException If the criteria array is empty or malformed.
     *
     * @example
     * foreach ($entityManager->streamBy(User::class, ['status' => 'active'], ['id' => 'ASC']) as $user) {
     *     echo $user->email . PHP_EOL;
     * }
     *
     * @see EntityManager::findBy() For non-streaming variant that returns a full array.
     */

    public function streamBy(
        string $entity,
        array $criteria,
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null
    ): Generator {
        [$table, $columns] = $this->getMetadata($entity);
        [$sql, $values] = $this->buildSelectQuery($table, $columns, $criteria, $orderBy, $limit, $offset);

        LogHelper::query($sql, [], $this->getLogger());
        $statement = $this->getDatabaseDriver()->prepare($sql);
        $this->bindParameters($statement, $values);
        $statement->execute();

        // Yield one entity at a time for efficient memory usage
        while ($row = $statement->fetch()) {
            yield $this->hydrateEntity($entity, $columns, $row);
        }
    }

    /**
     * Binds parameters to a prepared database statement.
     *
     * This method is a utility to bind an associative array of values to the corresponding
     * named parameters in a SQL statement before execution. It ensures clean separation
     * of query logic and data, preventing SQL injection and improving readability.
     *
     * @param Statement $statement The database statement to bind parameters to.
     * @param array<string, mixed> $values Associative array of parameter names (e.g., ":id") and their corresponding values.
     *
     * @return void
     *
     * @example
     * $statement = $driver->prepare("SELECT * FROM users WHERE id = :id");
     * $entityManager->bindParameters($statement, [':id' => 42]);
     * $statement->execute();
     */
    public function bindParameters(Statement $statement, array $values): void
    {
        foreach ($values as $param => $value) {
            $statement->bindValue($param, $value);
        }
    }

    /**
     * Builds a comma-separated list of quoted column names for use in SELECT queries.
     *
     * This method extracts the actual column names from the entity metadata,
     * quotes them using the database driver's quoting mechanism, and concatenates them
     * into a string suitable for use in the `SELECT` clause of a SQL query.
     *
     * Duplicate column names are removed using `array_unique()` to avoid SQL errors.
     *
     * @param array $columns The column metadata array (usually from MetadataParser).
     *
     * @return string A comma-separated string of quoted column names (e.g., "`id`, `username`, `email`").
     *
     * @example
     * // Given metadata for columns:
     * // [
     * //   'id' => ['column' => 'id'],
     * //   'username' => ['column' => 'username'],
     * // ]
     * //
     * // Result: "`id`, `username`"
     */
    private function buildSelectFields(array $columns): string
    {
        $fields = [];

        foreach ($columns as $column) {
            $fields[] = $this->databaseDriver->quoteIdentifier($column["column"]);
        }

        return implode(", ", array_unique($fields));
    }

    /**
     * Builds a parameterized SQL SELECT query string with optional WHERE, ORDER BY, LIMIT, and OFFSET clauses.
     *
     * This helper method dynamically constructs a safe SQL SELECT statement based on the provided criteria
     * and pagination options. Unknown columns in the criteria (i.e. those not present in the entity metadata)
     * are automatically filtered out. It is used internally by methods such as {@see EntityManager::findBy()},
     * {@see EntityManager::findOneBy()}, and {@see EntityManager::streamBy()}.
     *
     * @param string $table Quoted table name (including identifier escaping).
     * @param array $columns Metadata describing the entity's columns, as returned from MetadataParser.
     * @param array $criteria Associative array of field => value pairs for the WHERE clause.
     *                        Any key not matching a valid column will be ignored.
     * @param array $orderBy Optional. Associative array of column => direction (ASC or DESC).
     * @param int|null $limit Optional. Maximum number of rows to return.
     * @param int|null $offset Optional. Number of rows to skip (useful for pagination).
     *
     * @return array{0: string, 1: array} An array with the SQL string and the bound parameter values.
     *
     * @throws InvalidArgumentException If no WHERE conditions are provided (empty $criteria).
     *
     * @example
     * [$sql, $params] = $this->buildSelectQuery(
     *     "`users`",
     *     $columns,
     *     ['status' => 'active', 'format' => 'xml'],
     *     ['created_at' => 'DESC'],
     *     10,
     *     0
     * );
     *
     * // Resulting SQL
     * // SELECT `id`, `username`, `email` FROM `users`
     * //   WHERE `status` = :status
     * //   ORDER BY `created_at` DESC LIMIT 10 OFFSET 0
     *
     * @see EntityManager::findBy()
     * @see EntityManager::findOneBy()
     * @see EntityManager::streamBy()
     */
    private function buildSelectQuery(
        string $table,
        array $columns,
        array $criteria,
        array $orderBy = [],
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $conditions = [];
        $params = [];
        $validColumnNames = array_column($columns, 'column');

        // Build WHERE clause based on provided criteria, filtering out unknown columns.
        foreach ($criteria as $name => $value) {
            if (!in_array($name, $validColumnNames, true)) {
                continue;
            }

            $quoted = $this->databaseDriver->quoteIdentifier($name);
            $conditions[] = "{$quoted} = :{$name}";
            $params[":{$name}"] = $value;
        }

        // Build base SELECT query.
        $fields = $this->buildSelectFields($columns);
        $sql = "SELECT {$fields} FROM {$table}";
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }

        // Append ORDER BY clause if specified
        if (!empty($orderBy)) {
            $orderParts = [];
            foreach ($orderBy as $field => $direction) {
                $quotedField = $this->databaseDriver->quoteIdentifier($field);
                $dir = strtoupper($direction) === "DESC" ? "DESC" : "ASC";
                $orderParts[] = "{$quotedField} {$dir}";
            }
            $sql .= " ORDER BY " . implode(", ", $orderParts);
        }

        // Add LIMIT and OFFSET clauses if set
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        if ($offset !== null) {
            $sql .= " OFFSET {$offset}";
        }

        return [$sql, $params];
    }

    /**
     * Instantiates and populates an entity object from raw database row data.
     *
     * This method uses the entity's metadata to map column names from a database result
     * to the corresponding entity properties. If an entity with the same primary key
     * already exists in the identity map, it will be reused to prevent duplicate instances.
     *
     * @param string $entity Fully-qualified class name of the entity (e.g., Entity\User::class).
     * @param array $columns Metadata describing the entity's columns, as returned from MetadataParser.
     * @param array $data Associative array containing column => value pairs from the database.
     *
     * @return object The hydrated (populated) entity instance.
     *
     * @example
     * $row = ['id' => 42, 'username' => 'john', 'email' => 'john@example.com'];
     * user = $this->hydrateEntity(User::class, $columns, $row);
     * echo $user->username; // john
     *
     * @see UnitOfWork::getFormIdentityMap()
     * @see UnitOfWork::storeInIdentityMap()
     */
    private function hydrateEntity(string $entity, array $columns, array $data): object
    {
        $primaryKeyId = $data[$this->getPrimaryKeyColumn($columns)];
        $existingEntity = $this->unitOfWork->getFormIdentityMap($entity, $primaryKeyId);

        if ($existingEntity !== null) {
            return $existingEntity; // Reuse cached instance
        }

        // @Todo Replace with Lazy-Loading Proxy
        // $entityInstance = new \Symfony\Component\VarExporter\LazyObject\LazyObjectState($entity);
        // $entityInstance = User::createLazy(fn() => $this->loadUserData(...));
        //
        // https://itsimiro.medium.com/lazy-objects-in-php-8-4-a-new-era-of-efficient-object-handling-ce4832a1143c
        // https://symfony.com/blog/revisiting-lazy-loading-proxies-in-php
        $entityInstance = new $entity();
        $this->unitOfWork->storeInIdentityMap($entity, $primaryKeyId, $entityInstance);

        // Assign database values to entity properties
        foreach ($columns as $property => $column) {
            if (isset($data[$column["column"]])) {
                $entityInstance->$property = $data[$column["column"]];
            }
        }

        return $entityInstance;
    }
}
