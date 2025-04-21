<?php

namespace ORM\Metadata\AttributeHandler;

use ORM\Attributes\Column;
use ORM\Attributes\GeneratedValue;
use ORM\Attributes\Id;
use ORM\Metadata\MetadataEntity;
use ReflectionProperty;

final readonly class IdAttributeHandler implements MetadataAttributeHandler
{

    public function supports(ReflectionProperty $property): bool
    {
        return !empty($property->getAttributes(Id::class));
    }

    public function build(ReflectionProperty $property, MetadataEntity $metadataEntity): void
    {
        $columnAttributes = $property->getAttributes(Column::class);
        $generatedValueAttributes = $property->getAttributes(GeneratedValue::class);
        $idColumnName = !empty($columnAttributes)
            ? ($columnAttributes[0]->newInstance()->name ?: $property->getName())
            : $property->getName();

        $metadataEntity->setPrimaryKey($idColumnName);
        $metadataEntity->setPrimaryKeyGenerated(!empty($generatedValueAttributes));
    }
}