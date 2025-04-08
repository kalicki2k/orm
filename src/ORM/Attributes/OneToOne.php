<?php

namespace ORM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToOne
{
    /**
     * @param string $entity The target entity for the association.
     * @param string|null $inversedBy The property name on the target entity that inversely maps this association.
     * @param string|null $mappedBy The property name on the target entity that owns this association.
     * @param array $cascade Array of cascade operations (e.g., ["persist", "remove"]).
     * @param string $fetch Fetch strategy: "LAZY" or "EAGER".
     * @param bool $orphanRemoval Whether orphan removal is enabled for the association.
     */
    public function __construct(
        public string $entity,
        public ?string $inversedBy = null,
        public ?string $mappedBy = null,
        public array $cascade = [],
        public string $fetch = "LAZY", // Todo: not implemented
        public bool $orphanRemoval = false // Todo: not implemented
    ) {}
}