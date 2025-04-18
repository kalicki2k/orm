<?php

namespace ORM\Persistence;

use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use ORM\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use ReflectionException;

final readonly class UpdateExecutor
{
    public function __construct(
        private DatabaseDriver   $databaseDriver,
        private MetadataParser   $metadataParser,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Executes an update for the given entity.
     *
     * @throws ReflectionException
     */
    public function execute(EntityBase $entity): void
    {
        new QueryBuilder($this->databaseDriver, $this->logger)
            ->update()
            ->fromMetadata(
                $this->metadataParser->parse($entity::class),
                null,
                $this->metadataParser->extract($entity),
            )
            ->execute();
    }
}
