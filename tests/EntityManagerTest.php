<?php

namespace Tests;

use ORM\EntityManager;
use ORM\Drivers\DatabaseDriver;
use PHPUnit\Framework\TestCase;
use ORM\Attributes\Table;
use ORM\Attributes\Column;

/**
 * Dummy entity class with #[Table] and #[Column] attributes
 * used for testing metadata extraction.
 */
#[Table(name: 'users')]
class DummyUserForEntityManager
{
    #[Column(name: 'id', type: 'int', primary: true)]
    public int $id;

    #[Column(name: 'username', type: 'string', length: 255)]
    public string $username;
}

/**
 * Unit tests for the EntityManager metadata functionality.
 */
class EntityManagerTest extends TestCase
{
    /**
     * Tests getMetadata() when passing an entity instance.
     * Verifies the correct table name and column attributes.
     */
    public function testGetMetadataFromEntity(): void
    {
        $driver = $this->createMock(DatabaseDriver::class);
        $entityManager = new EntityManager($driver);

        $entity = new DummyUserForMetadata();
        [$table, $columns] = $entityManager->getMetadata($entity);

        $this->assertEquals('users', $table);
        $this->assertArrayHasKey('id', $columns);
        $this->assertEquals('int', $columns['id']['type']);
        $this->assertTrue($columns['id']['primary']);
    }

    /**
     * Tests getMetadata() when passing the entity class name as string.
     * Verifies that metadata is correctly extracted statically.
     */
    public function testGetMetadataFromClassName(): void
    {
        $driver = $this->createMock(DatabaseDriver::class);
        $entityManager = new EntityManager($driver);

        [$table, $columns] = $entityManager->getMetadata(DummyUserForMetadata::class);

        $this->assertEquals('users', $table);
        $this->assertArrayHasKey('username', $columns);
        $this->assertEquals('string', $columns['username']['type']);
        $this->assertEquals(255, $columns['username']['length']);
    }
}