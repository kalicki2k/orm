<?php

namespace ORM\Metadata\AttributeHandler;

use ORM\Attributes\Column;
use ORM\Metadata\MetadataEntity;
use ReflectionProperty;

class ColumnAttributeHandler implements MetadataAttributeHandler
{
    public function supports(ReflectionProperty $property): bool
    {
        return !empty($property->getAttributes(Column::class));
    }

    public function build(ReflectionProperty $property, MetadataEntity $metadataEntity): void
    {
        /** @var Column $column */
        $column = $property->getAttributes(Column::class)[0]->newInstance();
        $column->name ??= $property->getName();

        $metadataEntity->addColumn($column);
    }
}