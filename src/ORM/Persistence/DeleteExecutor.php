<?php

namespace ORM\Persistence;

use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use ORM\Query\Expression;
use ORM\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use ReflectionException;
use RuntimeException;

final readonly class DeleteExecutor
{
    public function __construct(
        private DatabaseDriver $databaseDriver,
        private MetadataParser $metadataParser,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * Executes deletion for the given entity.
     *
     * @throws ReflectionException
     */
    public function execute(EntityBase $entity): void
    {
        $metadata = $this->metadataParser->parse($entity::class);
        $primaryKey = $metadata->getPrimaryKey();
        $primaryKeyValue = $this->metadataParser->extract($entity)[$primaryKey] ?? null;

        if ($primaryKeyValue === null) {
            throw new RuntimeException("Cannot delete entity without identifier.");
        }

        new QueryBuilder($this->databaseDriver, $this->logger)
            ->delete()
            ->table($metadata->getTable())
            ->where(Expression::eq($primaryKey, $primaryKeyValue))
            ->execute();
    }
}
