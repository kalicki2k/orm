<?php

namespace ORM;

use DateMalformedStringException;
use DateTimeImmutable;
use InvalidArgumentException;
use ORM\Drivers\DatabaseDriver;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;
use ORM\Query\QueryBuilder;
use ORM\Util\ReflectionCacheInstance;
use Psr\Log\LoggerInterface;
use ReflectionException;

/**
 * The central access point for ORM operations on entities.
 *
 * The EntityManager handles querying, persisting, and retrieving entity objects.
 */
class EntityManager {
    protected QueryBuilder $queryBuilder;

    /**
     * EntityManager constructor.
     *
     * @param DatabaseDriver $databaseDriver The database driver implementation (e.g., PDO-based).
     * @param MetadataParser $metadataParser The metadata parser used to read entity mappings.
     * @param LoggerInterface|null $logger Optional PSR-3 logger for SQL or debug output.
     */
    public function __construct(
        private readonly DatabaseDriver $databaseDriver,
        private readonly MetadataParser $metadataParser,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->queryBuilder = new QueryBuilder($this->databaseDriver, $this->logger);
    }

    /**
     * Finds and returns an entity instance by its primary key.
     *
     * @param string $entityName The fully qualified class name of the entity.
     * @param int|string|array|null $conditions The primary key value or an associative array of key-value pairs for composite keys.
     * @param array $relations
     * @return object|null The entity object if found, or null if no match is found.
     *
     * @throws InvalidArgumentException If the entity does not have a primary key defined.
     * @throws DateMalformedStringException
     * @throws ReflectionException
     */
    public function find(string $entityName, int|string|array|null $conditions = null, array $relations = []): ?object
    {

        $metadata = $this->getMetadata($entityName);
        $statement = clone $this->queryBuilder->select()->fromMetadata($metadata, $conditions)->execute();
        $data = $statement->fetch();

        if (!$data) {
            return null;
        }

        return $this->hydrateEntity($metadata, $data);
    }

    /**
     * Retrieves the parsed metadata for a given entity class.
     *
     * @param string $entityName The fully qualified class name of the entity.
     *
     * @return MetadataEntity The parsed metadata information.
     *
     * @throws ReflectionException
     */
    protected function getMetadata(string $entityName): MetadataEntity
    {
        return $this->metadataParser->parse($entityName);
    }

    /**
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    protected function hydrateEntity(MetadataEntity $metadata, array $data): object
    {
        $reflection = ReflectionCacheInstance::getInstance()->get($metadata->getEntityName());
        $entity = $reflection->newInstanceWithoutConstructor();

        foreach ($metadata->getColumns() as $property => $column) {
            $name = "{$metadata->getAlias()}_{$column["name"]}";

            if (!array_key_exists($name, $data)) {
                continue;
            }

            $value = $this->hydrateColumn($data[$name], $column["type"] ?? null);
            $reflectionProperty = $reflection->getProperty($property);
            $reflectionProperty->setValue($entity, $value);
        }

        return $entity;
    }

    /**
     * @throws DateMalformedStringException
     */
    protected function hydrateColumn(mixed $value, ?string $type = null): mixed
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

    protected function hydrateRelation(array $relations, array $data): ?object
    {
        // Todo implement...
        return new \stdClass();
    }
}
