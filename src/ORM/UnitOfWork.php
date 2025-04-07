<?php

namespace ORM;

use ORM\Logger\LogHelper;
use ORM\Util\ReflectionCache;
use Ramsey\Uuid\Uuid;
use ReflectionException;

/**
 * Class UnitOfWork
 *
 * Manages persistence operations for entities such as inserts, updates, and deletes.
 * Collects changes and executes them in a single transaction-like operation via `commit()`.
 */
class UnitOfWork
{
    /** @var array<object> Entities scheduled for insert */
    private array $newEntities = [];

    /** @var array<object> Entities scheduled for update */
    private array $updatedEntities = [];

    /** @var array<object> Entities scheduled for deletion */
    private array $removedEntities = [];

    /** @var array<string, object> Identity map for preventing duplicate hydration */
    private array $identityMap = [];

    /**
     * UnitOfWork constructor.
     *
     * @param EntityManager $entityManager The associated entity manager
     */
    public function __construct(private readonly EntityManager $entityManager) {}

    /**
     * Schedules an entity for insertion on flush.
     *
     * @param object $entity The entity to insert.
     */
    public function scheduleInsert(object $entity): void
    {
        if (!in_array($entity, $this->newEntities, true)) {
            $this->newEntities[] = $entity;
        }
    }

    /**
     * Schedules an entity for update on flush.
     *
     * @param object $entity The entity to update.
     */
    public function scheduleUpdate(object $entity): void
    {
        $this->updatedEntities[] = $entity;
    }

    /**
     * Schedules an entity for deletion on flush.
     *
     * @param object $entity The entity to delete.
     */
    public function scheduleDelete(object $entity): void
    {
        $this->removedEntities[] = $entity;
    }

    /**
     * Executes all scheduled changes (insert, update, delete) in order.
     */
    public function commit(): void
    {
        foreach ($this->removedEntities as $entity) {
            $this->commitDelete($entity);
        }

        foreach ($this->updatedEntities as $entity) {
            $this->commitUpdate($entity);
        }

        foreach ($this->newEntities as $entity) {
            $this->commitInsert($entity);
        }

        $this->clear();
    }

    /**
     * Returns an entity from the identity map if it exists.
     *
     * @param string $class The fully qualified class name.
     * @param string|int $id The entity ID.
     * @return object|null
     */
    public function getFormIdentityMap(string $class, string|int $id): ?object
    {
        return $this->identityMap["{$class}:{$id}"] ?? null;
    }

    /**
     * Stores an entity in the identity map.
     *
     * @param string $class The class name.
     * @param string|int $id The entity ID.
     * @param object $entity The entity instance.
     */
    public function storeInIdentityMap(string $class, string|int $id, object $entity): void
    {
        $this->identityMap["{$class}:{$id}"] = $entity;
    }

    private function applyGeneratedPrimaryKey(object $entity, array $columns): void
    {
        foreach ($columns as $property => $column) {
            if (
                ($column["primary"] ?? false) &&
                ($column["type"] === "uuid") &&
                empty($entity->$property)
            ) {
                $entity->$property = Uuid::uuid4()->toString();
            }
        }
    }

    private function assignAutoIncrementId(object $entity, array $columns, string $table): void
    {
        foreach ($columns as $property => $column) {
            if (($column["primary"] ?? false) && ($column["autoIncrement"] ?? false)) {
                $id = $this->entityManager->getDatabaseDriver()->lastInsertId($table, $column["column"]);
                $entity->$property = is_numeric($id) ? (int)$id : $id;
            }
        }
    }


    /**
     * Performs an INSERT SQL operation for a new entity.
     *
     * @param object $entity The new entity to insert.
     * @throws ReflectionException
     */
    private function commitInsert(object $entity): void
    {
        [$table, $columns] = $this->entityManager->getMetadata($entity);
        [$fields, $placeholders, $values] = $this->buildInsertParts($entity, $columns);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->entityManager->getDatabaseDriver()->quoteIdentifier($table),
            implode(", ", $fields),
            implode(", ", $placeholders)
        );

        $this->execute($sql, $values);
        $this->assignAutoIncrementId($entity, $columns, $table);
    }

    /**
     * Performs an UPDATE SQL operation for a modified entity.
     *
     * @param object $entity The entity to update.
     * @throws ReflectionException
     */
    private function commitUpdate(object $entity): void
    {
        [$table, $columns] = $this->entityManager->getMetadata($entity);

        $assignments = [];
        $conditions = [];
        $values = [];

        foreach ($columns as $property => $column) {
            $columnName = $column['column'];
            $quoted = $this->entityManager->getDatabaseDriver()->quoteIdentifier($columnName);

            if ($column['primary']) {
                $conditions[] = "{$quoted} = :pk_{$columnName}";
                $values[":pk_{$columnName}"] = $entity->$property;
            } else {
                $assignments[] = "{$quoted} = :{$columnName}";
                $values[":{$columnName}"] = $entity->$property;
            }
        }

        if (empty($conditions)) {
            throw new \RuntimeException("Cannot update entity without primary key.");
        }

        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $this->entityManager->getDatabaseDriver()->quoteIdentifier($table),
            implode(", ", $assignments),
            implode(" AND ", $conditions)
        );

        $this->execute($sql, $values);
    }

    /**
     * Performs a DELETE SQL operation for a removed entity.
     *
     * @param object $entity The entity to delete.
     * @throws ReflectionException
     */
    private function commitDelete(object $entity): void
    {
        [$table, $columns] = $this->entityManager->getMetadata($entity);

        $conditions = [];
        $values = [];

        foreach ($columns as $property => $column) {
            if ($column['primary']) {
                $columnName = $column['column'];
                $quoted = $this->entityManager->getDatabaseDriver()->quoteIdentifier($columnName);
                $conditions[] = "{$quoted} = :{$columnName}";
                $values[":{$columnName}"] = $entity->$property;
            }
        }

        if (empty($conditions)) {
            throw new \RuntimeException("Cannot delete entity without primary key.");
        }

        $sql = sprintf(
            "DELETE FROM %s WHERE %s",
            $this->entityManager->getDatabaseDriver()->quoteIdentifier($table),
            implode(" AND ", $conditions)
        );
        $this->execute($sql, $values);
    }

    /**
     * Generates insert query components: fields, placeholders, and bound values.
     *
     * @param object $entity The entity to insert.
     * @param array $columns Metadata array of columns
     * @return array Tuple: [fields[], placeholders[], values]
     * @throws ReflectionException
     */
    private function buildInsertParts(object $entity, array $columns): array
    {
        $reflectionClass = ReflectionCache::get($entity);

        $fields = [];
        $placeholders = [];
        $values = [];

        $this->applyGeneratedPrimaryKey($entity, $columns);

        foreach ($columns as $property => $column) {
            if (!empty($column["autoIncrement"])) {
                continue;
            }

            $field = $this->entityManager->getDatabaseDriver()->quoteIdentifier($column["column"]);
            $reflectionProperty = $reflectionClass->getProperty($property);

            if (!$reflectionProperty->isInitialized($entity)) {
                // Handle uninitialized nullable or defaulted properties
                if (!empty($column["nullable"])) {
                    $fields[] = $field;
                    $placeholders[] = ":{$column["column"]}";
                    $values[":{$column["column"]}"] = null;
                } elseif (array_key_exists("default", $column)) {
                    $fields[] = $field;
                    $placeholders[] = ":{$column["column"]}";
                    $values[":{$column["column"]}"] = $column["default"];
                }
                continue;
            }

            $fields[] = $field;
            $placeholders[] = ":{$column["column"]}";
            $values[":{$column["column"]}"] = $entity->$property;
        }

        return [$fields, $placeholders, $values];
    }

    /**
     * Clears all internal buffers after commit.
     */
    private function clear(): void
    {
        $this->newEntities = [];
        $this->updatedEntities = [];
        $this->removedEntities = [];
        $this->identityMap = [];
    }

    /**
     * Executes a SQL statement with parameter binding and logging.
     *
     * @param string $sql
     * @param array $values
     */
    private function execute(string $sql, array $values): void
    {
        LogHelper::query($sql, $values, $this->entityManager->getLogger());

        $statement = $this->entityManager->getDatabaseDriver()->prepare($sql);
        $this->entityManager->bindParameters($statement, $values);
        $statement->execute();
    }
}
