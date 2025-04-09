<?php

namespace ORM\Attributes;

use Attribute;

/**
 * Marks a class as a database entity.
 *
 * This attribute is used to indicate that a class should be managed by the ORM
 * and persisted in the database. Typically used in combination with #[Table]
 * to define the corresponding database table.
 *
 * Example:
 *   #[Entity]
 *   #[Table(name: "users")]
 *   class User {
 *       // ...
 *   }
 *
 * @see \ORM\Attributes\Table
 * @see \ORM\Metadata\MetadataParser
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Entity {}
