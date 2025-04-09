<?php

namespace ORM\Attributes;

use Attribute;


/**
 * Specifies the details of a foreign key column in an entity relationship.
 *
 * Used in combination with relationship attributes like #[OneToOne] to define how the
 * foreign key is mapped in the database.
 *
 * Example:
 *   #[OneToOne(entity: User::class)]
 *   #[JoinColumn(name: "user_id", referencedColumn: "id")]
 *   public User $user;
 *
 * @see \ORM\Attributes\OneToOne
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class JoinColumn
{
    /**
     * @param string $name The name of the foreign key column.
     * @param string $referencedColumn The name of the column in the target entity that is referenced.
     * @param bool $nullable Whether the column allows NULL values. Defaults to true.
     * @param bool $unique Whether the column must have unique values. Defaults to false.
     */
    public function __construct(
        public string $name,
        public string $referencedColumn,
        public bool $nullable = true,
        public bool $unique = false,
    ) {}
}