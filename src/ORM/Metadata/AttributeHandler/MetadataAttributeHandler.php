<?php

namespace ORM\Metadata\AttributeHandler;

use ReflectionProperty;
use ORM\Metadata\MetadataEntity;

interface MetadataAttributeHandler
{
    public function supports(ReflectionProperty $property): bool;

    public function build(ReflectionProperty $property, MetadataEntity $metadataEntity): void;
}