<?php

namespace ORM\Attributes;

use Attribute;

/**
 * Specifies the table name for an entity.
 *
 * This attribute is used in combination with #[Entity] to map a class to a specific database table.
 * If omitted, the table name might be inferred from the class name.
 *
 * @example
 * ```php
 * #[Entity]
 * #[Table("users")]
 * class User
 * {
 *     // ...
 * }
 * ```
 *
 * @see Entity
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Table
{
    /**
     * @param string $name The name of the table in the database.
     */
    public function __construct(public string $name) {}
}
