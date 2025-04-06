<?php

namespace Tests\Attributes;

use ORM\Attributes\Column;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DummyEntityForColumn {
    #[Column(name: "id", type: "int", length: 10, primary: true, autoIncrement: true)]
    public int $id;

    #[Column(name: "username")]
    public string $username;
}

class ColumnTest extends TestCase
{
    public function testColumnAttributeOnIdProperty(): void
    {
        $ref = new ReflectionClass(DummyEntityForColumn::class);
        $property = $ref->getProperty('id');
        $attributes = $property->getAttributes(Column::class);

        $this->assertCount(1, $attributes, "Expected one Column attribute for id property.");

        $column = $attributes[0]->newInstance();
        $this->assertInstanceOf(Column::class, $column);
        $this->assertEquals("id", $column->name);
        $this->assertEquals("int", $column->type);
        $this->assertEquals(10, $column->length);
        $this->assertTrue($column->primary);
        $this->assertTrue($column->autoIncrement);
        $this->assertFalse($column->nullable);
    }

    public function testColumnAttributeOnUsernameProperty(): void
    {
        $ref = new ReflectionClass(DummyEntityForColumn::class);
        $property = $ref->getProperty('username');
        $attributes = $property->getAttributes(Column::class);

        $this->assertCount(1, $attributes, "Expected one Column attribute for username property.");

        $column = $attributes[0]->newInstance();
        $this->assertInstanceOf(Column::class, $column);
        $this->assertEquals("username", $column->name);
        $this->assertEquals("string", $column->type);
        $this->assertNull($column->length);
        $this->assertFalse($column->primary);
        $this->assertFalse($column->autoIncrement);
    }
}