<?php

namespace ORM\Entity;

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
use RuntimeException;

/**
 * The central access point for ORM operations on entities.
 *
 * The EntityManager handles querying, persisting, and retrieving entity objects.
 */
readonly class EntityManager {
    /**
     * EntityManager constructor.
     *
     * @param DatabaseDriver $databaseDriver The database driver implementation (e.g., PDO-based).
     * @param MetadataParser $metadataParser The metadata parser used to read entity mappings.
     * @param LoggerInterface|null $logger Optional PSR-3 logger for SQL or debug output.
     */
    public function __construct(
        private DatabaseDriver $databaseDriver,
        private MetadataParser $metadataParser,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function persist(EntityBase $entity): self
    {
        $metadata = $this->metadataParser->parse($entity::class);
        $data = $this->metadataParser->extract($entity, true);

        new QueryBuilder($this->databaseDriver, $this->logger)->insert()->fromMetadata($metadata, $data)->execute();

        return $this;
    }

    /**
     * @throws ReflectionException
     */
    public function update(EntityBase $entity): self
    {
        $metadata = $this->metadataParser->parse($entity::class);
        $data = $this->metadataParser->extract($entity);

        new QueryBuilder($this->databaseDriver, $this->logger)->update()->fromMetadata($metadata, $data)->execute();

        return $this;
    }

    public function delete(EntityBase $entity): self
    {
        $metadata = $this->metadataParser->parse($entity::class);
        $data = $this->metadataParser->extract($entity);
        $id = $data[$metadata->getPrimaryKey()];

        if ($id === null) {
            throw new RuntimeException("Cannot delete entity without identifier.");
        }

        new QueryBuilder($this->databaseDriver, $this->logger)->delete()->fromMetadata($metadata, $id)->execute();
        return $this;
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
        $statement = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata(
                $metadata,
                $conditions,
                fn(string $class) => $this->getMetadata($class),
                $relations,
            )
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
            $reflection->getProperty($property)->setValue($entity, $value);
        }

        foreach ($metadata->getRelations() as $property => $relation) {
            $related = $this->hydrateRelation($metadata, $property, $relation, $data);

            if ($related !== null) {
                $reflection->getProperty($property)->setValue($entity, $related);
            }
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

    /**
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    protected function hydrateRelation(
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $data
    ): ?object {
        $relationAlias = $parentMetadata->getRelationAlias($property);
        $alias = "{$relationAlias}_";

        $relationData = array_filter(
            $data,
            fn($key) => str_starts_with($key, $alias),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($relationData) || count(array_filter($relationData, fn($v) => $v !== null)) === 0) {
            return null;
        }

        $relatedMetadata = $this->getMetadata($relation["relation"]->entity);
        $relatedMetadata->setAlias($parentMetadata->getRelationAlias($property));

        return $this->hydrateEntity($relatedMetadata, $data);
    }
}
