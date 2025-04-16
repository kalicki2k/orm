<?php

namespace ORM\Metadata\AttributeHandler;

use ORM\Attributes\JoinColumn;
use ORM\Attributes\OneToOne;
use ORM\Metadata\MetadataEntity;
use ReflectionProperty;

class OneToOneAttributeHandler implements MetadataAttributeHandler
{

    public function supports(ReflectionProperty $property): bool
    {
        return !empty($property->getAttributes(OneToOne::class));
    }

    public function build(ReflectionProperty $property, MetadataEntity $metadataEntity): void
    {
        $oneToOne = $property->getAttributes(OneToOne::class)[0]->newInstance();
        $joinColumnAttributes = $property->getAttributes(JoinColumn::class);
        $joinColumn = !empty($joinColumnAttributes)
            ? $joinColumnAttributes[0]->newInstance()
            : null;

        $metadataEntity->addRelation($property->getName(), $oneToOne, $joinColumn);

    }
}