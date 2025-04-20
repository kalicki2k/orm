<?php

namespace ORM\Metadata\AttributeHandler;

use ORM\Attributes\OneToMany;
use ORM\Metadata\MetadataEntity;
use ReflectionProperty;

class OneToManyAttributeHandler implements MetadataAttributeHandler
{

    public function supports(ReflectionProperty $property): bool
    {
        return !empty($property->getAttributes(OneToMany::class));
    }

    public function build(ReflectionProperty $property, MetadataEntity $metadataEntity): void
    {
        /** @var OneToMany $oneToMany */
        $oneToMany = $property->getAttributes(OneToMany::class)[0]->newInstance();
        $metadataEntity->addRelation($property->getName(), $oneToMany);
    }
}