<?php

namespace ORM\Attributes;

use Attribute;
use InvalidArgumentException;

/**
 * Specifies the table that a database entity is mapped to.
 *
 * This attribute should be used in combination with #[Entity] to mark a class as a database entity
 * and to define the name of the corresponding table.
 *
 * Example:
 *   #[Entity]
 *   #[Table(name: "users")]
 *   class User {
 *       // ...
 *   }
 *
 * @see \ORM\Attributes\Entity
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    /**
     * Creates a new Table attribute instance.
     *
     * @param string $name The name of the table in the database.
     */
    public function __construct(public string $name) {}
}