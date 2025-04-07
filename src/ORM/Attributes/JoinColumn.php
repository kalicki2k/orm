<?php

namespace ORM\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class JoinColumn
{
    /**
     * @param string $name
     * @param string $referencedColumn
     */
    public function __construct(
        public string $name,
        public string $referencedColumn,
//        public ?string $foreignKeyConstraint = null, // @todo
    ) {}
}