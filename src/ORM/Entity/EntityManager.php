<?php

namespace ORM\Entity;

use DateMalformedStringException;
use DateTimeImmutable;
use Generator;
use InvalidArgumentException;
use ORM\Drivers\DatabaseDriver;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;
use ORM\Query\QueryBuilder;
use ORM\Relation\EagerOneToOneHydrator;
use ORM\Relation\LazyOneToOneHydrator;
use ORM\Relation\RelationHydrator;
use ORM\UnitOfWork;
use ORM\Util\ReflectionCacheInstance;
use Psr\Log\LoggerInterface;
use ReflectionException;

/**
 * The central access point for ORM operations on entities.
 *
 * The EntityManager handles querying, persisting, and retrieving entity objects.
 */
readonly class EntityManager {
    /** @var RelationHydrator[] */
    private array $relationHydrators;

    private UnitOfWork $unitOfWork;

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
    ) {
        $this->unitOfWork = new UnitOfWork($this->databaseDriver, $this->metadataParser, $this->logger);
        $this->relationHydrators = [
            new LazyOneToOneHydrator($this),
            new EagerOneToOneHydrator($this),
        ];
    }

    /**
     * @throws ReflectionException
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
     * @throws ReflectionException
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
     * @throws ReflectionException
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
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public function findAll(string $entityName, array $relations = []): array
    {
        $metadata = $this->getMetadata($entityName);
        $statement = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata(
                $metadata,
                [],
                fn(string $class) => $this->getMetadata($class),
                $relations
            )
            ->execute();

        $results = [];
        while ($row = $statement->fetch()) {
            $results[] = $this->hydrateEntity($metadata, $row);
        }

        return $results;
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
    public function findBy(string $entityName, int|string|array|null $conditions = null, array $relations = []): ?object
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
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public function streamAll(string $entityName, array $relations = []): Generator
    {
        $metadata = $this->getMetadata($entityName);
        $statement = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata(
                $metadata,
                [],
                fn(string $class) => $this->getMetadata($class),
                $relations
            )
            ->execute();

        while ($row = $statement->fetch()) {
            yield $this->hydrateEntity($metadata, $row);
        }
    }

    /**
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public function streamBy(string $entityName, array $criteria = [], array $relations = []): Generator
    {
        $metadata = $this->getMetadata($entityName);
        $statement = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata(
                $metadata,
                $criteria,
                fn(string $class) => $this->getMetadata($class),
                $relations
            )
            ->execute();

        while ($row = $statement->fetch()) {
            yield $this->hydrateEntity($metadata, $row);
        }
    }

    /**
     * @throws ReflectionException
     */
    public function flush(): void
    {
        $this->unitOfWork->commit();
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
    public function getMetadata(string $entityName): MetadataEntity
    {
        return $this->metadataParser->parse($entityName);
    }

    /**
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    public function hydrateEntity(MetadataEntity $metadata, array $data): EntityBase
    {
        $reflection = ReflectionCacheInstance::getInstance();
        $entity = $reflection->get($metadata->getEntityName())->newInstanceWithoutConstructor();

        $this->hydrateColumns($entity, $metadata, $data);
        $this->hydrateRelations($entity, $metadata, $data);

        $entity->__takeSnapshot($this->metadataParser->extract($entity));

        return $entity;
    }


    /**
     * @throws DateMalformedStringException
     * @throws ReflectionException
     */
    private function hydrateColumns(EntityBase $entity, MetadataEntity $metadata, array $data): void
    {
        $reflection = ReflectionCacheInstance::getInstance();

        foreach ($metadata->getColumns() as $property => $column) {
            $name = "{$metadata->getAlias()}_{$column["name"]}";

            if (!array_key_exists($name, $data)) {
                continue;
            }

            $value = $this->hydrateColumn($data[$name], $column["type"] ?? null);
            $reflection->setValue($entity, $property, $value);
        }
    }

    /**
     * @throws DateMalformedStringException
     */
    private function hydrateColumn(mixed $value, ?string $type = null): mixed
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
     */
    private function hydrateRelations(EntityBase $entity, MetadataEntity $metadata, array $data): void
    {
        $reflection = ReflectionCacheInstance::getInstance();

        foreach ($metadata->getRelations() as $property => $relation) {
            $related = $this->hydrateRelation($metadata, $property, $relation, $data);

            if ($related !== null) {
                $reflection->setValue($entity, $property, $related);
            }
        }
    }


    private function hydrateRelation(
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $data
    ): ?object
    {
        foreach ($this->relationHydrators as $hydrator) {
            if ($hydrator->supports($relation)) {
                return $hydrator->hydrate($parentMetadata, $property, $relation, $data);
            }
        }

        return null;
    }
}
