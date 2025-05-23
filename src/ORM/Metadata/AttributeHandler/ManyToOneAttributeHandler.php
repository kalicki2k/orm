<?php

namespace ORM\Metadata\AttributeHandler;

use ORM\Attributes\JoinColumn;
use ORM\Attributes\ManyToOne;
use ORM\Attributes\Column;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;
use ReflectionException;
use ReflectionProperty;

final readonly class ManyToOneAttributeHandler implements MetadataAttributeHandler
{
    public function __construct(private MetadataParser $parser) {}

    public function supports(ReflectionProperty $property): bool
    {
        return !empty($property->getAttributes(ManyToOne::class));
    }

    /**
     * @throws ReflectionException
     */
    public function build(ReflectionProperty $property, MetadataEntity $metadataEntity): void
    {
        /** @var ManyToOne $manyToOne */
        $manyToOne = $property->getAttributes(ManyToOne::class)[0]->newInstance();
        $joinColumnAttr = $property->getAttributes(JoinColumn::class)[0] ?? null;
        /** @var JoinColumn|null $joinColumn */
        $joinColumn = $joinColumnAttr?->newInstance();

        if ($joinColumn !== null) {
            $targetMetadata = $this->parser->parse($manyToOne->entity);
            $refColumn = $targetMetadata->getColumns()[$joinColumn->referencedColumn] ?? null;
            $type = $refColumn["attributes"]->type ?? "int";

            $column = new Column($type, $joinColumn->name, null, $joinColumn->nullable);
            $metadataEntity->addColumn($column);
        }

        $metadataEntity->addRelation($property->getName(), $manyToOne, $joinColumn);
    }
}
