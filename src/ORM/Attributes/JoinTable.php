<?php

namespace ORM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JoinTable
{
    public function __construct(
        public string $name,
        public string $joinColumn,
        public string $inverseJoinColumn,
    ) {}
}
