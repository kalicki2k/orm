<?php

namespace ORM\Attributes;

use Attribute;
use ORM\Entity\Type\FetchType;

/**
 * Defines a one-to-one relationship between two entities.
 *
 * This attribute is used to mark a property as a one-to-one association.
 * Supports cascade behavior, fetch type, and bidirectional mapping.
 *
 * - `entity` defines the related class.
 * - `mappedBy` is used on the inverse side of a bidirectional relation.
 * - `cascade` allows propagation of operations like persist or remove.
 * - `fetch` defines if the relation should be loaded lazily (default) or eagerly.
 *
 * @example
 * ```php
 * #[OneToOne(
 *     entity: Profile::class,
 *     cascade: [CascadeType::Persist, CascadeType::Remove],
 *     fetch: FetchType::Lazy
 * )]
 * #[JoinColumn(name: "profile_id", referencedColumn: "id")]
 * private Profile|Closure $profile;
 * ```
 *
 * @see JoinColumn
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToOne
{
    /**
     * @param string $entity The fully qualified class name of the related entity.
     * @param string|null $mappedBy The property name in the related entity that maps this side (inverse side).
     * @param array|null $cascade List of CascadeType enums (e.g. Persist, Remove).
     * @param FetchType $fetch Whether to eagerly or lazily load the relation (default = Lazy).
     */
    public function __construct(
        public string $entity,
        public ?string $mappedBy = null,
        public ?array $cascade = null,
        public FetchType $fetch = FetchType::Lazy,
    ) {}
}
