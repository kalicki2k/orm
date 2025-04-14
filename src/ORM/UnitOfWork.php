<?php

namespace ORM;

use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Entity\Type\CascadeType;
use ORM\Metadata\MetadataParser;
use ORM\Query\QueryBuilder;
use ORM\Util\ReflectionCacheInstance;
use Psr\Log\LoggerInterface;
use ReflectionException;
use RuntimeException;
use WeakMap;

class UnitOfWork
{
    private WeakMap $scheduledForInsert;
    private WeakMap $scheduledForUpdate;
    private WeakMap $scheduledForDelete;

    public function __construct(
        private readonly DatabaseDriver $databaseDriver,
        private readonly MetadataParser $metadataParser,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->scheduledForInsert = new WeakMap();
        $this->scheduledForUpdate = new WeakMap();
        $this->scheduledForDelete = new WeakMap();
    }

    /**
     * @throws ReflectionException
     */
    public function scheduleForInsert(EntityBase $entity): void
    {
        if (isset($this->scheduledForInsert[$entity])) {
            return;
        }

        if ($entity->__isPersisted()) {
            return;
        }

        $this->scheduledForInsert[$entity] = true;
        $this->handleCascades($entity, CascadeType::Persist);
    }

    /**
     * @throws ReflectionException
     */
    public function scheduleForUpdate(EntityBase $entity): void
    {
        if (isset($this->scheduledForUpdate[$entity])) {
            return;
        }

        if (!$entity->__isDirty($this->metadataParser->extract($entity))) {
            return;
        }

        $this->scheduledForUpdate[$entity] = true;
    }

    /**
     * @throws ReflectionException
     */
    public function scheduleForDelete(EntityBase $entity): void
    {
        if (isset($this->scheduledForDelete[$entity])) {
            return;
        }

        if (!$entity->__isPersisted()) {
            return;
        }

        $this->scheduledForDelete[$entity] = true;
        $this->handleCascades($entity, CascadeType::Remove);
    }

    /**
     * @throws ReflectionException
     */
    public function commit(): void
    {
        foreach ($this->scheduledForDelete as $entity => $_) {
            $this->executeDelete($entity);
        }

        foreach ($this->scheduledForInsert as $entity => $_) {
            $this->executeInsert($entity);
        }

        foreach ($this->scheduledForUpdate as $entity => $_) {
            $this->executeUpdate($entity);
        }

        $this->clear();
    }

    private function clear(): void
    {
        $this->scheduledForInsert = new WeakMap();
        $this->scheduledForUpdate = new WeakMap();
        $this->scheduledForDelete = new WeakMap();
    }

    /**
     * @throws ReflectionException
     */
    private function executeInsert(EntityBase $entity): void
    {
        [$metadata, $data] = $this->getMetadata($entity, true);

        $lastInsertId = new QueryBuilder($this->databaseDriver, $this->logger)
            ->insert()
            ->fromMetadata($metadata, $data)
            ->execute();

        // @Todo Use caching!!!
        $reflection = ReflectionCacheInstance::getInstance()
            ->get($entity)
            ->getProperty($metadata->getPrimaryKey());
        $reflection->setValue($entity, $lastInsertId);

        $entity->__markPersisted($this->metadataParser->extract($entity));
    }

    /**
     * @throws ReflectionException
     */
    private function executeUpdate(EntityBase $entity): void
    {
        [$metadata, $data] = $this->getMetadata($entity);

        new QueryBuilder($this->databaseDriver, $this->logger)
            ->update()
            ->fromMetadata($metadata, $data)
            ->execute();
    }

    /**
     * @throws ReflectionException
     */
    private function executeDelete(EntityBase $entity): void
    {
        [$metadata, $data] = $this->getMetadata($entity);
        $id = $data[$metadata->getPrimaryKey()];

        if ($id === null) {
            throw new RuntimeException("Cannot delete entity without identifier.");
        }

        new QueryBuilder($this->databaseDriver, $this->logger)->delete()->fromMetadata($metadata, $id)->execute();
    }

    /**
     * @throws ReflectionException
     */
    private function getMetadata(EntityBase $entity, bool $excludePrimaryKey = false): array
    {
        return [
            $this->metadataParser->parse($entity::class),
            $this->metadataParser->extract($entity, $excludePrimaryKey),
        ];
    }

    /**
     * @throws ReflectionException
     */
    private function handleCascades(EntityBase $entity, CascadeType $action): void
    {
        $metadata = $this->metadataParser->parse($entity::class);
        $reflection = ReflectionCacheInstance::getInstance();

        foreach ($metadata->getRelations() as $property => $relationInfo) {
            $reflectionProperty = $reflection->getProperty($entity, $property);

            if (!$reflectionProperty->isInitialized($entity)) {
                continue;
            }

            $cascade = $relationInfo["relation"]->cascade ?? [];
            $relatedEntity = $reflection->getValue($entity, $property);

            if (!($relatedEntity instanceof EntityBase)) {
                continue;
            }

            if (!in_array($action, $cascade, true)) {
                continue;
            }

            match ($action) {
                CascadeType::Persist => $this->scheduleForInsert($relatedEntity),
                CascadeType::Remove => $this->scheduleForDelete($relatedEntity),
            };
        }
    }
}