<?php

namespace ORM;

use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Entity\Type\CascadeType;
use ORM\Metadata\MetadataParser;
use ORM\Persistence\CascadeHandler;
use ORM\Persistence\DeleteExecutor;
use ORM\Persistence\DeleteSchedule;
use ORM\Persistence\InsertExecutor;
use ORM\Persistence\InsertSchedule;
use ORM\Persistence\JoinTableExecutor;
use ORM\Persistence\JoinTableSchedule;
use ORM\Persistence\UpdateExecutor;
use ORM\Persistence\UpdateSchedule;
use Psr\Log\LoggerInterface;
use ReflectionException;

/**
 * The UnitOfWork class manages the lifecycle of entities and their persistence state.
 * It tracks changes (inserts, updates, deletes) and commits them to the database in a single transaction.
 */
class UnitOfWork
{
    private InsertExecutor $insertExecutor;
    private UpdateExecutor $updateExecutor;
    private DeleteExecutor $deleteExecutor;
    private JoinTableExecutor $joinTableExecutor;
    private CascadeHandler $cascadeHandler;
    private InsertSchedule $insertSchedule;
    private UpdateSchedule $updateSchedule;
    private DeleteSchedule $deleteSchedule;
    private JoinTableSchedule $joinTableSchedule;

    /**
     * Constructor for the UnitOfWork.
     *
     * @param DatabaseDriver $databaseDriver The database driver for executing queries.
     * @param MetadataParser $metadataParser The metadata parser for entity reflection and metadata handling.
     * @param LoggerInterface|null $logger Optional logger for debugging and logging operations.
     */
    public function __construct(
        readonly DatabaseDriver $databaseDriver,
        private readonly MetadataParser $metadataParser,
        readonly ?LoggerInterface $logger = null,
    ) {
        $this->insertExecutor = new InsertExecutor($databaseDriver, $this->metadataParser, $logger);
        $this->updateExecutor = new UpdateExecutor($databaseDriver, $this->metadataParser, $logger);
        $this->deleteExecutor = new DeleteExecutor($databaseDriver, $this->metadataParser, $logger);
        $this->cascadeHandler = new CascadeHandler($this->metadataParser, $this);
        $this->insertSchedule = new InsertSchedule($this->metadataParser);
        $this->updateSchedule = new UpdateSchedule();
        $this->deleteSchedule = new DeleteSchedule();

        $this->joinTableSchedule = new JoinTableSchedule($this->metadataParser);
        $this->joinTableExecutor = new JoinTableExecutor($databaseDriver, $this->metadataParser, $this->logger);
    }

    /**
     * Schedules an entity for insertion into the database.
     *
     * @param EntityBase $entity The entity to be inserted.
     * @throws ReflectionException
     */
    public function scheduleForInsert(EntityBase $entity): void
    {
        if ($entity->__isPersisted()) {
            return;
        }

        $this->insertSchedule->schedule($entity);
        $this->cascadeHandler->handle($entity, CascadeType::Persist);
        $this->joinTableSchedule->scheduleForInsert($entity);
    }

    /**
     * Schedules an entity for update in the database.
     *
     * @param EntityBase $entity The entity to be updated.
     * @throws ReflectionException
     */
    public function scheduleForUpdate(EntityBase $entity): void
    {
        if (!$entity->__isDirty($this->metadataParser->extract($entity))) {
            return;
        }

        $this->updateSchedule->schedule($entity);

        // @todo: Collection needs a history of changes
        $this->joinTableSchedule->scheduleForDelete($entity);
        $this->joinTableSchedule->scheduleForInsert($entity);
    }

    /**
     * Schedules an entity for deletion from the database.
     *
     * @param EntityBase $entity The entity to be deleted.
     * @throws ReflectionException
     */
    public function scheduleForDelete(EntityBase $entity): void
    {
        if (!$entity->__isPersisted()) {
            return;
        }

        $this->deleteSchedule->schedule($entity);
        $this->cascadeHandler->handle($entity, CascadeType::Remove);
        $this->joinTableSchedule->scheduleForDelete($entity);
    }

    /**
     * Commits all scheduled operations (insert, update, delete) to the database.
     *
     * @throws ReflectionException If reflection fails during execution.
     */
    public function commit(): void
    {
        foreach ($this->deleteSchedule->getAll() as $entity) {
            $this->executeDelete($entity);
        }

        foreach ($this->insertSchedule->getAll() as $entity) {
            $this->executeInsert($entity);
        }

        foreach ($this->updateSchedule->getAll() as $entity) {
            $this->executeUpdate($entity);
        }

        $this->joinTableExecutor->execute($this->joinTableSchedule);

        $this->insertSchedule->clear();
        $this->updateSchedule->clear();
        $this->deleteSchedule->clear();
        $this->joinTableSchedule->clear();
    }

    /**
     * Executes the insertion of an entity.
     *
     * @param EntityBase $entity The entity to be inserted.
     * @throws ReflectionException If reflection fails during execution.
     */
    private function executeInsert(EntityBase $entity): void
    {
        $this->insertExecutor->execute($entity);
    }

    /**
     * Executes the update of an entity.
     *
     * @param EntityBase $entity The entity to be updated.
     * @throws ReflectionException If reflection fails during execution.
     */
    private function executeUpdate(EntityBase $entity): void
    {
        $this->updateExecutor->execute($entity);
    }

    /**
     * Executes the deletion of an entity.
     *
     * @param EntityBase $entity The entity to be deleted.
     * @throws ReflectionException If reflection fails during execution.
     */
    private function executeDelete(EntityBase $entity): void
    {
        $this->deleteExecutor->execute($entity);
    }
}