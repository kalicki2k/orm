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
        $this->queryBuilder = new QueryBuilder($this->databaseDriver);
    }

    /**
     * Finds and returns an entity instance by its primary key.
     *
     * @param string $entityName The fully qualified class name of the entity.
     * @param mixed $id The primary key value or an associative array of key-value pairs for composite keys.
     * @return object|null The entity object if found, or null if no match is found.
     *
     * @throws InvalidArgumentException If the entity does not have a primary key defined.
     */
    public function find(string $entityName, mixed $id, array $relations = []): ?object
    {
        $metadata = $this->getMetadata($entityName);
        $select = [];
        $where = [];
        $parameters = [];

        foreach ($metadata->getColumns() as $column) {
            $select[] = $column["name"];
        }

        foreach ($metadata->getRelations() as $relation) {
            if (array_key_exists("joinColumn", $relation)) {
                $select[] = $relation["joinColumn"]->name;
            }
        }

        if (!is_array($id)) {
            $primaryKey = $metadata->getPrimaryKey();

            if (!isset($primaryKey)) {
                throw new InvalidArgumentException("Primary key does not exist");
            }

            $where[$primaryKey] = ":{$primaryKey}";
            $parameters[$primaryKey] = $id;
        } else {
            foreach ($id as $key => $value) {
                $where[$key] = ":{$key}";
                $parameters[$key] = $value;
            }
        }

        $statement = clone $this->queryBuilder->table($metadata->getTable())
            ->select($select)
            ->where($where, $parameters)
            ->execute();

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
    protected function hydrateEntity(MetadataEntity $metadataEntity, array $data): object
    {
        $reflection = ReflectionCacheInstance::getInstance()->get($metadataEntity->getEntityName());
        $entity = $reflection->newInstanceWithoutConstructor();

        foreach ($metadataEntity->getColumns() as $property => $column) {
            if (!array_key_exists($column["name"], $data)) {
                continue;
            }

            $value = $this->hydrateColumn($data[$column["name"]], $column["type"] ?? null);
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

    }
}
