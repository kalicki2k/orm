<?php

namespace ORM\Query\Builder;

use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryBuilder;

/**
 * Builds SQL JOINs for EAGER-loaded entity relations.
 *
 * This builder processes requested relations (via 'joins' option) and adds the appropriate
 * LEFT JOIN clauses and aliased SELECTs to the query. It skips relations not explicitly requested
 * and respects the FetchType (only joins EAGER relations).
 *
 * @example
 * ```php
 * $queryBuilder->fromMetadata($metadata, $resolveMetadata, ['joins' => ['profile']]);
 * ```
 *
 * @see QueryBuilder
 * @see MetadataEntity
 */
final class JoinBuilder
{
    /**
     * Applies LEFT JOINs to the QueryBuilder for EAGER-loaded relations.
     *
     * @param QueryBuilder $builder The active query being built.
     * @param MetadataEntity $metadata Metadata of the root entity.
     * @param string[] $joins The list of relation names to JOIN (must match relation names in entity).
     * @param callable $resolveMetadata A function that takes a class name and returns MetadataEntity.
     */
    public function apply(
        QueryBuilder $builder,
        MetadataEntity $metadata,
        array $joins,
        callable $resolveMetadata
    ): void {
        foreach ($metadata->getRelations() as $property => $relationData) {
            $relation = $relationData["relation"] ?? null;

            if (!$relation || !in_array($property, $joins, true)) {
                continue;
            }

            if ($relation->fetch === FetchType::Lazy) {
                continue;
            }

            $relatedMetadata = $resolveMetadata($relation->entity);
            $joinAlias = $metadata->getRelationAlias($property);
            $joinTable = $relatedMetadata->getTable();
            $on = null;

            if (property_exists($relation, "mappedBy") && $relation->mappedBy !== null) {
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
