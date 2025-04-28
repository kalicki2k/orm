<?php

namespace ORM\Metadata\AttributeHandler;

use ORM\Attributes\JoinTable;
use ORM\Attributes\ManyToMany;
use ORM\Metadata\MetadataEntity;
use ReflectionProperty;
use RuntimeException;

class ManyToManyAttributeHandler
{
    public function supports(ReflectionProperty $property): bool
    {
        return !empty($property->getAttributes(ManyToMany::class));
    }

    public function build(ReflectionProperty $property, MetadataEntity $metadataEntity): void
    {
        /** @var ManyToMany $manyToMany */
        $manyToMany = $property->getAttributes(ManyToMany::class)[0]->newInstance();
        $joinTableAttr = $property->getAttributes(JoinTable::class)[0] ?? null;
        $joinTable = $joinTableAttr?->newInstance();

        if ($joinTable === null) {
            throw new RuntimeException("JoinTable is required for ManyToMany relation on property {$property->getName()}");
        }

        $metadataEntity->addRelation($property->getName(), $manyToMany, $joinTable);
    }
}