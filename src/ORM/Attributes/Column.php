<?php

namespace ORM\Attributes;

use Attribute;
use InvalidArgumentException;

/**
 * Defines a column mapping for a class property.
 *
 * This attribute is used to map an entity property to a specific column in the database.
 * It allows configuration of the column's data type, name, length, nullability, and default value.
 *
 * Example:
 *   #[Column(type: "string", name: "username", length: 255, nullable: false)]
 *   public string $username;
 *
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * Constructs a new Column attribute.
     *
     * @param string $type The data type of the column (e.g., "string", "int", "bool", "datetime").
     * @param string|null $name Optional custom column name. Defaults to the property name.
     * @param int|null $length Optional length for types like "string" (e.g., VARCHAR).
     * @param bool $nullable Whether the column allows NULL values. Defaults to false.
     * @param mixed $default An optional default value used when none is provided during insert.
     */
    public function __construct(
        public string $type,
        public ?string $name = null,
        public ?int $length = null,
        public bool $nullable = false,
        public mixed $default = null,
    ) {}
}