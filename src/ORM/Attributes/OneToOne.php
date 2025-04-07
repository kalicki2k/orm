<?php

namespace ORM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class OneToOne
{
    public function __construct(public string $entity) {}
}