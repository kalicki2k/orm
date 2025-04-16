<?php

namespace ORM\Persistence;

use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use ORM\Query\QueryBuilder;
use ORM\Util\ReflectionCacheInstance;
use Psr\Log\LoggerInterface;
use ReflectionException;

final readonly class InsertExecutor
{
    public function __construct(
        private DatabaseDriver   $databaseDriver,
        private MetadataParser   $metadataParser,
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
            ->fromMetadata($metadata, $data)
            ->execute();

        if ($metadata->isPrimaryKeyGenerated()) {
            $primaryKey = $metadata->getPrimaryKey();

            $reflection = ReflectionCacheInstance::getInstance()
                ->get($entity)
                ->getProperty($primaryKey);

            $reflection->setValue($entity, $lastInsertId);
        }

        $entity->__markPersisted($this->metadataParser->extract($entity));
    }
}
