<?php

namespace Tests\Drivers;

use ORM\Drivers\PDODriver;
use PHPUnit\Framework\TestCase;

/**
 * Tests the PDODriver and PDOStatementAdapter.
 */
class PDODriverTest extends TestCase
{
    /**
     * @var PDODriver The PDODriver instance used for testing.
     */
    private PDODriver $driver;

    /**
     * Sets up the test environment.
     *
     * Creates a PDODriver instance that uses an in-memory SQLite database.
     * This provides an isolated and fast testing environment.
     */
    protected function setUp(): void
    {
        $this->driver = new PDODriver('sqlite::memory:');
        $this->driver->connect();
    }

    /**
     * Tests the quoteIdentifier method.
     *
     * @covers \ORM\Drivers\PDODriver::quoteIdentifier
     */
    public function testQuoteIdentifier(): void
    {
        $this->assertEquals('`test`', $this->driver->quoteIdentifier('test'));
    }

    /**
     * Tests the connect, prepare, execute, and lastInsertId methods.
     *
     * This test creates a table, inserts a record, retrieves the last insert ID,
     * and then queries the inserted record to verify that all operations work as expected.
     *
     * @covers \ORM\Drivers\PDODriver::connect
     * @covers \ORM\Drivers\PDODriver::prepare
     * @covers \ORM\Drivers\PDODriver::lastInsertId
     * @covers \ORM\Drivers\PDOStatementAdapter
     */
    public function testPrepareExecuteAndLastInsertId(): void
    {
        // Create table
        $createStmt = $this->driver->prepare("CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");
        $this->assertTrue($createStmt->execute(), 'Table creation failed');

        // Insert a record
        $insertStmt = $this->driver->prepare("INSERT INTO test (name) VALUES (:name)");
        $insertStmt->bindValue(':name', 'Alice');
        $this->assertTrue($insertStmt->execute(), 'Insertion failed');

        // Retrieve last insert ID
        $lastId = $this->driver->lastInsertId();
        $this->assertNotEmpty($lastId, 'Last insert ID should not be empty');

        // Query to fetch the inserted record
        $selectStmt = $this->driver->prepare("SELECT * FROM test WHERE id = :id");
        $selectStmt->bindValue(':id', $lastId);
        $this->assertTrue($selectStmt->execute(), 'Select query failed');
        $row = $selectStmt->fetch();
        $this->assertIsArray($row, 'Expected an array to be returned');
        $this->assertEquals('Alice', $row['name']);
    }

    /**
     * Tests the fetchAll method of the PDOStatementAdapter.
     *
     * This test creates a table, inserts multiple records, and then retrieves all records
     * using fetchAll to verify that the method returns the expected number of rows and data.
     *
     * @covers \ORM\Drivers\PDOStatementAdapter::fetchAll
     */
    public function testFetchAll(): void
    {
        // Create table
        $createStmt = $this->driver->prepare("CREATE TABLE test (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)");
        $this->assertTrue($createStmt->execute(), 'Table creation failed');

        // Insert multiple records
        $names = ['Alice', 'Bob', 'Charlie'];
        foreach ($names as $name) {
            $insertStmt = $this->driver->prepare("INSERT INTO test (name) VALUES (:name)");
            $insertStmt->bindValue(':name', $name);
            $this->assertTrue($insertStmt->execute(), "Insertion failed for $name");
        }

        // Retrieve all records
        $selectStmt = $this->driver->prepare("SELECT * FROM test ORDER BY id");
        $this->assertTrue($selectStmt->execute(), 'Select query failed');
        $rows = $selectStmt->fetchAll();
        $this->assertCount(3, $rows, 'There should be 3 rows returned');
        $this->assertEquals($names, array_column($rows, 'name'));
    }
}
