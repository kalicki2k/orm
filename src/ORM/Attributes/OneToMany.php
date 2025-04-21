<?php

namespace ORM\Attributes;

use Attribute;
use ORM\Entity\Type\FetchType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToMany
{
    public function __construct(
        public string $entity,
        public ?string $mappedBy = null,
        public ?array $cascade = null,
        public FetchType $fetch = FetchType::Eager,
    ){}
}