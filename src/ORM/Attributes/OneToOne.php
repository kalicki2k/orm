<?php

namespace ORM\Attributes;

use Attribute;


/**
 * Defines a one-to-one relationship between two entities.
 *
 * This attribute is used to map a property as a one-to-one association with another entity.
 * Can be configured to specify the owning or inverse side of the relationship.
 *
 * Example:
 *   #[OneToOne(entity: Profile::class, inversedBy: "user")]
 *   #[JoinColumn(name: "profile_id", referencedColumn: "id")]
 *   public Profile $profile;
 *
 * @see \ORM\Attributes\JoinColumn
 * @see \ORM\\MetadataParser
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToOne
{
    /**
     * @param string $entity The target entity class name.
     * @param string|null $mappedBy The property name in the target entity that owns the relationship (inverse side).
     */
    public function __construct(
        public string $entity,
        public ?string $mappedBy = null,
        public array $cascade = [],
    ) {}
}