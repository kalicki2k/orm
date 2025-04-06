<?php

namespace ORM\Attributes;

use Attribute;
use InvalidArgumentException;

/**
 * Marks a class as a database entity and maps it to a database table.
 *
 * Example usage:
 *   #[Table(name: "users")]
 *   class User {
 *       // ...
 *   }
 *
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    /**
     * Constructs a new Table attribute instance.
     *
     * @param string $name Name of the table in the database (must be non-empty).
     *
     * @throws InvalidArgumentException If the table name is empty.
     */
    public function __construct(public string $name)
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException("Table name cannot be empty.");
        }
    }
}