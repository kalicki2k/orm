<?php

namespace ORM\Query\Builder;

use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryBuilder;

final class JoinBuilder
{
    /**
     * @param QueryBuilder $builder
     * @param MetadataEntity  $metadata The parent entity metadata
     * @param string[] $eagerRelations Only relations explicitly requested
     * @param callable $resolveMetadata Resolves related entity metadata
     */
    public function apply(
        QueryBuilder $builder,
        MetadataEntity $metadata,
        array $eagerRelations,
        callable $resolveMetadata
    ): void {
        foreach ($metadata->getRelations() as $property => $relationData) {
            $relation = $relationData["relation"] ?? null;

            if (!$relation || !in_array($property, $eagerRelations, true)) {
                continue;
            }

            $relatedMetadata = $resolveMetadata($relation->entity);
            $joinAlias = $metadata->getRelationAlias($property);
            $joinTable = $relatedMetadata->getTable();
            $on = null;

            if ($relation->mappedBy !== null) {
                $owning = $relatedMetadata->getRelations()[$relation->mappedBy];
                $joinColumn = $owning["joinColumn"];

                if ($joinColumn) {
                    $on = "$joinAlias.$joinColumn->name = {$metadata->getAlias()}.$joinColumn->referencedColumn";
                }
            } else {
                $joinColumn = $relationData["joinColumn"];

                if ($joinColumn) {
                    $on = "{$metadata->getAlias()}.$joinColumn->name = $joinAlias.$joinColumn->referencedColumn";
                }
            }

            if ($on) {
                $builder->leftJoin($joinTable, $joinAlias, $on);

                foreach ($relatedMetadata->getColumns() as $column) {
                    $builder->select(["$joinAlias.{$column["name"]}" => "{$joinAlias}_{$column["name"]}"]);
                }
            }
        }
    }
}
