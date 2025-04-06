<?php

namespace Tests\Attributes;

use ORM\Attributes\Table;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[Table(name: "users")]
class DummyEntity {}

class TableTest extends TestCase
{
    /**
     * @coversNothing
     */
    public function testTableAttributeReadsCorrectly(): void
    {
        $ref = new ReflectionClass(DummyEntity::class);
        $attributes = $ref->getAttributes(Table::class);

        $this->assertCount(1, $attributes, "Expected one Table attribute.");

        $table = $attributes[0]->newInstance();

        $this->assertInstanceOf(Table::class, $table);
        $this->assertEquals("users", $table->name);
    }
}