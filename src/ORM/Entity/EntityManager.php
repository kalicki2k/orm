<?php

namespace ORM\Entity;

use Generator;
use InvalidArgumentException;
use ORM\Cache\EntityCache;
use ORM\Cache\InMemoryEntityCache;
use ORM\Cache\ReflectionCache;
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
 * The EntityManager handles querying, persisting, and retrieving entity objects.
 */
class EntityManager {
    private UnitOfWork $unitOfWork;

    private ReflectionCache $reflectionCache;

    private Hydrator $hydrator;

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
        private readonly EntityCache $entityCache = new InMemoryEntityCache(),
    ) {
        $this->reflectionCache = $metadataParser->getReflectionCache();
        $this->unitOfWork = new UnitOfWork($this->databaseDriver, $this->metadataParser, $this->entityCache, $this->logger);
        $this->hydrator = new EntityHydrator($this, $this->metadataParser, $this->reflectionCache, $this->entityCache);
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
     */
    public function findAll(string $entityName, array $options = []): array
    {
        $metadata = $this->getMetadata($entityName);
        $statement = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata(
                $metadata,
                fn(string $class) => $this->getMetadata($class),
                [],
                null,
                $options,
            )
            ->execute();

        $results = [];
        while ($row = $statement->fetch()) {
            $id = $row[$metadata->getAlias() . "_" . $metadata->getPrimaryKey()] ?? null;

            if (is_scalar($id)) {
                $cached = $this->entityCache->get($entityName, $id);
                if ($cached !== null) {
                    $results[] = $cached;
                    continue;
                }
            }

            $results[] = $this->hydrateEntity($metadata, $row);
        }

        return $results;
    }

    /**
     * Finds and returns an entity instance by its primary key.
     *
     * @param string $entityName The fully qualified class name of the entity.
     * @param Expression|int|string|array|null $criteria
     * @param array $options
     * @return object|null The entity object if found, or null if no match is found.
     *
     * @throws ReflectionException
     */
    public function findBy(string $entityName, Expression|int|string|array|null $criteria = null, array $options = []): ?object
    {
        if (is_scalar($criteria)) {
            $cached = $this->entityCache->get($entityName, $criteria);
            if ($cached !== null) {
                return $cached;
            }
        }

        $metadata = $this->getMetadata($entityName);
        $statement = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata(
                $metadata,
                fn(string $class) => $this->getMetadata($class),
                [],
                $this->normalizeCriteria($criteria, $metadata),
                $options,
            )
            ->execute();

        $data = $statement->fetch();
        return $data ? $this->hydrateEntity($metadata, $data) : null;
    }

    /**
     * @throws ReflectionException
     */
    public function streamAll(string $entityName, array $options = []): Generator
    {
        $metadata = $this->getMetadata($entityName);
        $statement = new QueryBuilder($this->databaseDriver, $this->logger)
            ->select()
            ->fromMetadata(
                $metadata,
                fn(string $class) => $this->getMetadata($class),
                [],
                null,
                $options
            )
            ->execute();

        while ($row = $statement->fetch()) {
            $id = $row[$metadata->getAlias() . "_" . $metadata->getPrimaryKey()] ?? null;

            if (is_scalar($id)) {
                $cached = $this->entityCache->get($entityName, $id);
                if ($cached !== null) {
                    yield $cached;
                    continue;
                }
            }

            yield $this->hydrateEntity($metadata, $row);
        }
    }

    /**
     * @throws ReflectionException
     */
    public function streamBy(string $entityName, Expression|int|string|array|null $criteria = null, array $options = []): Generator
    {
        $metadata = $this->getMetadata($entityName);
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
            $id = $row[$metadata->getAlias() . "_" . $metadata->getPrimaryKey()] ?? null;

            if (is_scalar($id)) {
                $cached = $this->entityCache->get($entityName, $id);
                if ($cached !== null) {
                    yield $cached;
                    continue;
                }
            }

            yield $this->hydrateEntity($metadata, $row);
        }
    }

    /**
     * @throws ReflectionException
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

    public function hydrateEntity(MetadataEntity $metadata, array $data): EntityBase
    {
        return $this->hydrator->hydrate($metadata, $data);
    }
}
