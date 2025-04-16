<?php

namespace Tests;

use ORM\Attributes\Column;
use ORM\Attributes\Table;
use ORM\Drivers\DatabaseDriver;
use ORM\Drivers\Statement;
use ORM\Entity\EntityManager;
use ORM\UnitOfWorkOld;
use PHPUnit\Framework\TestCase;

/**
 * Dummy entity class used for testing the UnitOfWork.
 */
#[Table(name: 'users')]
class DummyUserForUnitOfWork
{
    #[Column(name: 'id', type: 'int', primary: true, autoIncrement: true)]
    public int $id;

    #[Column(name: 'name', type: 'string', length: 255)]
    public string $name;
}

/**
 * Tests the behavior of UnitOfWork with mocked database interactions.
 */
class UnitOfWorkTest extends TestCase
{
    /**
     * Tests that an entity scheduled for insertion gets an ID assigned
     * after the commit, simulating an auto-incremented primary key.
     */
    public function testInsertSchedulesNewEntity(): void
    {
        $entity = new DummyUserForUnitOfWork();
        $entity->name = 'Alice';

        $driver = $this->createMock(DatabaseDriver::class);
        $driver->method('quoteIdentifier')->willReturnCallback(fn($v) => "`$v`");
        $driver->method('prepare')->willReturn($this->createMock(Statement::class));
        $driver->method('lastInsertId')->willReturn(1);

        $em = new EntityManager($driver);
        $unitOfWork = new UnitOfWorkOld($em);

        $unitOfWork->scheduleInsert($entity);
        $unitOfWork->commit();

        // Verify that the auto-increment ID was applied
        $this->assertEquals(1, $entity->id);
    }

    /**
     * Tests that scheduling an update generates SQL and executes without exceptions.
     *
     * It ensures `execute()` is called on the prepared statement.
     */
    public function testUpdateCreatesValidSQL(): void
    {
        $entity = new DummyUserForUnitOfWork();
        $entity->id = 10;
        $entity->name = 'Bob';

        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())->method('execute');

        $driver = $this->createMock(DatabaseDriver::class);
        $driver->method('quoteIdentifier')->willReturnCallback(fn($v) => "`$v`");
        $driver->method('prepare')->willReturn($stmt);

        $em = new EntityManager($driver);
        $unitOfWork = new UnitOfWorkOld($em);
        $unitOfWork->scheduleUpdate($entity);
        $unitOfWork->commit();

        // Basic check: no exception is thrown and SQL is executed
        $this->assertTrue(true);
    }

    /**
     * Tests that scheduling a delete generates correct SQL and executes.
     *
     * It simulates the deletion of an entity by ID.
     */
    public function testDeleteCreatesValidSQL(): void
    {
        $entity = new DummyUserForUnitOfWork();
        $entity->id = 5;
        $entity->name = 'Charlie';

        $stmt = $this->createMock(Statement::class);
        $stmt->expects($this->once())->method('execute');

        $driver = $this->createMock(DatabaseDriver::class);
        $driver->method('quoteIdentifier')->willReturnCallback(fn($v) => "`$v`");
        $driver->method('prepare')->willReturn($stmt);

        $em = new EntityManager($driver);
        $unitOfWork = new UnitOfWorkOld($em);
        $unitOfWork->scheduleDelete($entity);
        $unitOfWork->commit();

        // Basic check: we assume SQL execution completed
        $this->assertTrue(true);
    }
}
