<?php

namespace ORM;

use ORM\Cache\EntityCache;
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
use SplObjectStorage;

class UnitOfWork
{
    private InsertExecutor $insertExecutor;
    private UpdateExecutor $updateExecutor;
    private DeleteExecutor $deleteExecutor;
    private CascadeHandler $cascadeHandler;
    private SplObjectStorage $scheduledForInsert;
    private SplObjectStorage $scheduledForUpdate;
    private SplObjectStorage $scheduledForDelete;

    public function __construct(
        readonly DatabaseDriver $databaseDriver,
        private readonly MetadataParser $metadataParser,
        readonly ?LoggerInterface $logger = null,
    ) {
        $this->insertExecutor = new InsertExecutor($databaseDriver, $metadataParser, $logger);
        $this->updateExecutor = new UpdateExecutor($databaseDriver, $metadataParser, $logger);
        $this->deleteExecutor = new DeleteExecutor($databaseDriver, $metadataParser, $logger);
        $this->cascadeHandler = new CascadeHandler($metadataParser, $this);
        $this->scheduledForInsert = new SplObjectStorage();
        $this->scheduledForUpdate = new SplObjectStorage();
        $this->scheduledForDelete = new SplObjectStorage();
    }

    /**
     * @throws ReflectionException
     */
    public function scheduleForInsert(EntityBase $entity): void
    {
        if ($this->scheduledForInsert->contains($entity)) {
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

        $this->scheduledForInsert->attach($entity);
        $this->handleCascades($entity, CascadeType::Persist);
    }

    /**
     * @throws ReflectionException
     */
    public function scheduleForUpdate(EntityBase $entity): void
    {
        if ($this->scheduledForUpdate->contains($entity)) {
            return;
        }

        if (!$entity->__isDirty($this->metadataParser->extract($entity))) {
            return;
        }

        $this->scheduledForUpdate->attach($entity);
    }

    /**
     * @throws ReflectionException
     */
    public function scheduleForDelete(EntityBase $entity): void
    {
        if ($this->scheduledForDelete->contains($entity)) {
            return;
        }

        if (!$entity->__isPersisted()) {
            return;
        }

        $this->scheduledForDelete->attach($entity);
        $this->handleCascades($entity, CascadeType::Remove);
    }

    /**
     * @throws ReflectionException
     */
    public function commit(): void
    {
        foreach ($this->scheduledForDelete as $entity) {
            $this->executeDelete($entity);
        }

        foreach ($this->sortInsertEntitiesByDependency() as $entity) {
            $this->insertExecutor->execute($entity);
        }

        foreach ($this->scheduledForUpdate as $entity) {
            $this->executeUpdate($entity);
        }

        $this->clear();
    }

    private function clear(): void
    {
        $this->scheduledForInsert = new SplObjectStorage();
        $this->scheduledForUpdate = new SplObjectStorage();
        $this->scheduledForDelete = new SplObjectStorage();
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

    private function sortInsertEntitiesByDependency(): array
    {
        $ordered = [];
        $visited = [];

        foreach ($this->scheduledForInsert as $entity) {
            $this->visit($entity, $ordered, $visited);
        }

        return $ordered;
    }

    private function visit(EntityBase $entity, array &$ordered, array &$visited): void
    {
        $hash = spl_object_hash($entity);
        if (isset($visited[$hash])) {
            return;
        }

        $visited[$hash] = true;

        $metadata = $this->metadataParser->parse($entity::class);
        $reflection = $this->metadataParser->getReflectionCache();

        foreach ($metadata->getRelations() as $property => $relationInfo) {
            $related = $reflection->getValue($entity, $property);

            if ($related instanceof \Closure) {
                $related = $related();
            }

            if ($related instanceof EntityBase && $this->scheduledForInsert->contains($related)) {
                $this->visit($related, $ordered, $visited);
            }
        }

        $ordered[] = $entity;
    }
}