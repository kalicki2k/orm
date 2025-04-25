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
        return !empty($property->getAttributes(Id::class))
            && !empty($property->getAttributes(Column::class));
    }

    public function build(ReflectionProperty $property, MetadataEntity $metadataEntity): void
    {
        /** @var Column $column */
        $column = $property->getAttributes(Column::class)[0]->newInstance();
        $idColumnName = $column->name ?: $property->getName();

        $metadataEntity->setPrimaryKey($idColumnName);
        $metadataEntity->setPrimaryKeyGenerated(!empty($property->getAttributes(GeneratedValue::class)));
    }
}