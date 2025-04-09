<?php

namespace ORM;

use ORM\Drivers\DatabaseDriver;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;
use Psr\Log\LoggerInterface;

class EntityManager {
    /**
     * EntityManager constructor.
     *
     * @param DatabaseDriver $databaseDriver The database driver implementation (e.g., PDO-based).
     * @param LoggerInterface|null $logger Optional PSR-3 logger for SQL and debug output.
     */
    public function __construct(
//        private readonly DatabaseDriver $databaseDriver,
        private readonly MetadataParser $metadataParser,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function find(string $entityName, mixed $id): ?object
    {
        $metadata = $this->getMetadata($entityName);

        var_dump($metadata);

        return $metadata;
    }

    protected function getMetadata(string $entityName): MetadataEntity
    {
        return $this->metadataParser->parse($entityName);
    }
}