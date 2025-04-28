<?php

namespace ORM\Persistence;

use InvalidArgumentException;
use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use ORM\Query\Expression;
use ORM\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use ReflectionException;

final readonly class UpdateExecutor
{
    public function __construct(
        private DatabaseDriver $databaseDriver,
        private MetadataParser $metadataParser,
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
        $primaryKey = $metadata->getPrimaryKey();
        $primaryKeyValue = $data[$primaryKey] ?? null;

        if ($primaryKeyValue === null) {
            throw new InvalidArgumentException("Missing primary key value for update");
        }


        new QueryBuilder($this->databaseDriver, $this->logger)
            ->update()
            ->table($metadata->getTable())
            ->values($data)
            ->where(Expression::eq($primaryKey, $primaryKeyValue))
            ->execute();
    }
}
