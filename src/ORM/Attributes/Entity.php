<?php

namespace ORM\Attributes;

use Attribute;

/**
 * Marks a class as a persistable database entity.
 *
 * This attribute is used to indicate that a class should be treated as a database entity.
 * The class will be mapped to a corresponding table and its annotated properties will
 * be used to define the columns and relationships.
 *
 * This attribute works in combination with:
 * - #[Table] to define the table name
 * - #[Column], #[Id], #[OneToOne], etc. to define the structure
 *
 * @example
 * ```php
 * #[Entity]
 * #[Table(name: "users")]
 * class User
 * {
 *     #[Id]
 *     #[Column(type: "int")]
 *     private int $id;
 *
 *     #[Column(type: "string", length: 255)]
 *     private string $username;
 * }
 * ```
 *
 * @see Table
 * @see Column
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Entity {}
