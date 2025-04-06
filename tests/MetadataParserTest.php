<?php

namespace Tests;

use ORM\Attributes\Column;
use ORM\Attributes\Table;
use ORM\MetadataParser;
use PHPUnit\Framework\TestCase;

#[Table(name: "users")]
class DummyUserForMetadata
{
    #[Column(name: "id", type: "int", primary: true, autoIncrement: true)]
    public int $id;

    #[Column(name: "username", type: "string", length: 255)]
    public string $username;

    #[Column(name: "email", type: "string", length: 255)]
    public string $email;
}
/**
 * @covers \ORM\MetadataParser
 */
class MetadataParserTest extends TestCase
{
    /**
     * Ensures that #[Table] and #[Column] attributes are parsed correctly.
     */
    public function testItParsesTableAndColumns(): void
    {
        $entity = new DummyUserForMetadata();
        [$table, $columns] = MetadataParser::parse($entity);

        // Check table name
        $this->assertSame('users', $table);

        // Check column keys
        $this->assertArrayHasKey('id', $columns);
        $this->assertArrayHasKey('username', $columns);
        $this->assertArrayHasKey('email', $columns);

        // Check column metadata
        $this->assertSame('id', $columns['id']['column']);
        $this->assertTrue($columns['id']['primary']);
        $this->assertTrue($columns['id']['autoIncrement']);

        $this->assertSame('username', $columns['username']['column']);
        $this->assertSame('string', $columns['username']['type']);
        $this->assertSame(255, $columns['username']['length']);
    }

    /**
     * Ensures that an exception is thrown when #[Table] is missing.
     */
    public function testItThrowsExceptionWithoutTableAttribute(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Missing #\[Table\] attribute/');

        $entity = new class {}; // anonymous class without #[Table] attribute
        MetadataParser::parse($entity);
    }
}