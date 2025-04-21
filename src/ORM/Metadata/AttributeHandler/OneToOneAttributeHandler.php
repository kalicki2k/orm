<?php

namespace ORM\Metadata\AttributeHandler;

use ORM\Attributes\Column;
use ORM\Attributes\JoinColumn;
use ORM\Attributes\OneToOne;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;
use ReflectionException;
use ReflectionProperty;

final readonly class OneToOneAttributeHandler implements MetadataAttributeHandler
{
    public function __construct(private MetadataParser $parser){}

    public function supports(ReflectionProperty $property): bool
    {
        return !empty($property->getAttributes(OneToOne::class));
    }

    /**
     * @throws ReflectionException
     */
    public function build(ReflectionProperty $property, MetadataEntity $metadataEntity): void
    {
        $oneToOne = $property->getAttributes(OneToOne::class)[0]->newInstance();
        $joinColumnAttributes = $property->getAttributes(JoinColumn::class);
        $joinColumn = !empty($joinColumnAttributes)
            ? $joinColumnAttributes[0]->newInstance()
            : null;

        if ($joinColumn !== null) {
            $targetMetadata = $this->parser->parse($oneToOne->entity);
            $referencedColumn = $targetMetadata->getColumns()[$joinColumn->referencedColumn];
            $type = $referencedColumn["attributes"]->type ?? "int";

            $column = new Column($type, $joinColumn->name, null, $joinColumn->nullable);
            $metadataEntity->addColumn($column);
        }

        $metadataEntity->addRelation($property->getName(), $oneToOne, $joinColumn);

    }
}