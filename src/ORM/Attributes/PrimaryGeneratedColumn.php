<?php

namespace ORM\Attributes;

use Attribute;

/**
 * Marks a property as a primary key column with auto-generation.
 *
 * This is a shortcut for defining primary key columns.
 * It automatically sets `primary=true`, and configures autoIncrement based on type.
 *
 * @example
 * #[PrimaryGeneratedColumn(name: "id", type: "uuid")]
 * public string $id;
 *
 * #[PrimaryGeneratedColumn(name: "id", type: "int")]
 * public int $id;
 *
 * @see Column
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class PrimaryGeneratedColumn extends Column
{
    /**
     * @param string $name Name of the database column.
     * @param string $type Data type, e.g. "int", "uuid", "string".
     * @param int|null $length Optional length for the column.
     * @param bool $nullable Whether the column is nullable.
     * @param mixed $default Optional default value.
     */
    public function __construct(
        public string $name,
        public string $type = 'int',
        public ?int $length = null,
        public bool $nullable = false,
        public mixed $default = null,
    ) {
        $autoIncrement = ($type === 'int' || $type === 'bigint');

        parent::__construct(
            name: $name,
            type: $type,
            length: $length,
            primary: true,
            autoIncrement: $autoIncrement,
            nullable: $nullable,
            default: $default
        );
    }
}