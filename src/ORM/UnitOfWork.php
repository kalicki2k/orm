<?php

namespace ORM;

use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Entity\Type\CascadeType;
use ORM\Metadata\MetadataParser;
use ORM\Persistence\CascadeHandler;
use ORM\Persistence\DeleteExecutor;
use ORM\Persistence\InsertExecutor;
use ORM\Persistence\UpdateExecutor;
use Psr\Log\LoggerInterface;
use ReflectionException;
use WeakMap;

class UnitOfWork
{
    private InsertExecutor $insertExecutor;
    private UpdateExecutor $updateExecutor;
    private DeleteExecutor $deleteExecutor;
    private CascadeHandler $cascadeHandler;
    private WeakMap $scheduledForInsert;
    private WeakMap $scheduledForUpdate;
    private WeakMap $scheduledForDelete;

    public function __construct(
        readonly DatabaseDriver $databaseDriver,
        private readonly MetadataParser $metadataParser,
        readonly ?LoggerInterface $logger = null,
    ) {
        $this->insertExecutor = new InsertExecutor($databaseDriver, $metadataParser, $logger);
        $this->updateExecutor = new UpdateExecutor($databaseDriver, $metadataParser, $logger);
        $this->deleteExecutor = new DeleteExecutor($databaseDriver, $metadataParser, $logger);
        $this->cascadeHandler = new CascadeHandler($metadataParser, $this);
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

        $metadata = $this->metadataParser->parse($entity::class);
        $reflection = $this->metadataParser->getReflectionCache();

        foreach ($metadata->getColumns() as $property => $column) {
            $default = $column['attributes']->default ?? null;

            if (!$reflection->isInitialized($entity, $property) && $default !== null) {
                $reflection->setValue($entity, $property, $default);
            }
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
        $this->insertExecutor->execute($entity);
    }

    /**
     * @throws ReflectionException
     */
    private function executeUpdate(EntityBase $entity): void
    {
        $this->updateExecutor->execute($entity);
    }

    /**
     * @throws ReflectionException
     */
    private function executeDelete(EntityBase $entity): void
    {
        $this->deleteExecutor->execute($entity);
    }

    /**
     * @throws ReflectionException
     */
    private function handleCascades(EntityBase $entity, CascadeType $action): void
    {
        $this->cascadeHandler->handle($entity, $action);
    }
}