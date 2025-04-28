<?php

namespace ORM\Attributes;

use Attribute;
use ORM\Entity\Type\FetchType;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ManyToMany
{
    public function __construct(
        public string $entity,
        public ?array $cascade = null,
        public FetchType $fetch = FetchType::Lazy
    ) {}
}