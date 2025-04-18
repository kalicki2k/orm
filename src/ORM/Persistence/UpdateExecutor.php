<?php

namespace ORM\Persistence;

use ORM\Cache\EntityCache;
use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use ORM\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use ReflectionException;

final readonly class UpdateExecutor
{
    public function __construct(
        private DatabaseDriver $databaseDriver,
        private MetadataParser $metadataParser,
        private EntityCache $entityCache,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Executes an update for the given entity.
     *
     * @throws ReflectionException
     */
    public function execute(EntityBase $entity): void
    {
        $metadata = $this->metadataParser->parse($entity::class);
        $data = $this->metadataParser->extract($entity);

        new QueryBuilder($this->databaseDriver, $this->logger)
            ->update()
            ->fromMetadata($metadata, null, $data)
            ->execute();



        $id = $this->metadataParser
            ->getReflectionCache()
            ->getValue($entity, $metadata->getPrimaryKey());

        if (is_scalar($id)) {
            $this->entityCache->set($entity::class, $id, $entity);
        }
    }
}
