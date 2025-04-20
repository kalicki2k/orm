<?php

namespace ORM\Attributes;

use Attribute;

/**
 * Marks a property as a column in the database table.
 *
 * This attribute is used to define the mapping between an entity's property and its corresponding
 * column in the database. It supports various options like data type, length, nullability, and default value.
 *
 * - `type`: The data type of the column (e.g., "string", "int", "datetime").
 * - `name`: Optional. The column name in the table. If omitted, the property name is used.
 * - `length`: Optional. Maximum length (mostly for strings).
 * - `nullable`: Whether the column can contain NULL values (default: false).
 * - `default`: Optional default value for the column.
 *
 * @example
 * ```php
 * #[Column(type: "string", name: "username", length: 255, nullable: false)]
 * private string $username;
 * ```
 *
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * @param string $type The column's data type (e.g. "string", "int", etc.).
     * @param string|null $name Optional name of the column in the table.
     * @param int|null $length Optional length (e.g. for VARCHAR).
     * @param bool $nullable Whether the column can be null (default: false).
     * @param mixed|null $default Optional default value.
     */
    public function __construct(
        public string $type,
        public ?string $name = null,
        public ?int $length = null,
        public bool $nullable = false,
        public mixed $default = null,
    ) {}
}