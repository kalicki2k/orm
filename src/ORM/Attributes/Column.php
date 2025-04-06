<?php

namespace ORM\Attributes;

use Attribute;
use InvalidArgumentException;

/**
 * Marks a class property as a database column and defines its mapping details.
 *
 * @example
 * #[Column(name: "email", type: "string", length: 255, nullable: false)]
 * public string $email;
 *
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    /**
     * Constructs a new Column attribute.
     *
     * @param string $name Name of the column in the database (must not be empty).
     * @param string $type Data type (e.g., "string", "int", "bool", "datetime"). Default is "string".
     * @param int|null $length Optional length (e.g., VARCHAR(255)). Null if not applicable.
     * @param bool $primary True if this column is part of the primary key.
     * @param bool $autoIncrement True if this column is auto-incremented.
     * @param bool $nullable True if the column allows NULL values.
     * @param mixed $default Optional default value (used if no value is set during insert).
     *
     * @throws InvalidArgumentException If $name is empty.
     */
    public function __construct(
        public string $name,
        public string $type = "string",
        public ?int $length = null,
        public bool $primary = false,
        public bool $autoIncrement = false,
        public bool $nullable = false,
        public mixed $default = null,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException("Column name cannot be empty.");
        }
    }
}