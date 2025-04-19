<?php

namespace ORM\Persistence;

use ORM\Cache\EntityCache;
use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use ORM\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use ReflectionException;

final readonly class InsertExecutor
{
    public function __construct(
        private DatabaseDriver $databaseDriver,
        private MetadataParser $metadataParser,
        private EntityCache $entityCache,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function execute(EntityBase $entity): void
    {
        $metadata = $this->metadataParser->parse($entity::class);
        $data = $this->metadataParser->extract($entity, excludePrimaryKey: true);

        $lastInsertId = new QueryBuilder($this->databaseDriver, $this->logger)
            ->insert()
            ->fromMetadata($metadata, null,  $data)
            ->execute();

        if ($metadata->isPrimaryKeyGenerated()) {
            $primaryKey = $metadata->getPrimaryKey();

            $reflection = $this->metadataParser->getReflectionCache();
            $reflection->getProperty($entity, $primaryKey)->setValue($entity, $lastInsertId);
        }

        $entity->__markPersisted($this->metadataParser->extract($entity));

        $id = $this->metadataParser
            ->getReflectionCache()
            ->getValue($entity, $metadata->getPrimaryKey());

        if (is_scalar($id)) {
            $this->entityCache->set($entity::class, $id, $entity);
        }
    }
}
