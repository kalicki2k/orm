<?php

namespace ORM\Attributes;

use Attribute;

/**
 * Specifies the details of a foreign key column in an entity relationship.
 *
 * This attribute is used to define how the owning side of a relation maps its foreign key
 * to the referenced column in the target entity.
 *
 * Often used in combination with relationship attributes such as #[OneToOne] or #[ManyToOne].
 *
 * - `name` is the name of the foreign key column in the current table.
 * - `referencedColumn` is the column name in the target entity (usually its primary key).
 * - `nullable` defines whether the FK column can be null (default: true).
 * - `unique` defines whether the FK column must be unique (for one-to-one mappings).
 *
 * @example
 * ```php
 * #[OneToOne(
 *     entity: Profile::class,
 *     cascade: [CascadeType::Persist, CascadeType::Remove]
 * )]
 * #[JoinColumn(name: "profile_id", referencedColumn: "id", nullable: true)]
 * private Profile|Closure $profile;
 * ```
 *
 * @see OneToOne
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