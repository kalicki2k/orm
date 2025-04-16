<?php

namespace ORM\Query\Builder;

use InvalidArgumentException;
use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryBuilder;

final class SelectBuilder
{
    public function apply(
        QueryBuilder $query,
        MetadataEntity $metadata,
        int|string|array|null $conditions = null,
        ?callable $resolveMetadata = null,
        array $eagerRelations = [],
    ): void {
        $select = [];
        $where = [];
        $parameters = [];

        // Root entity columns
        foreach ($metadata->getColumns() as $column) {
            $select["{$metadata->getAlias()}.{$column["name"]}"] = "{$metadata->getColumnAlias($column["name"])}";
        }

        // Lazy FK (e.g. profile_id)
        foreach ($metadata->getRelations() as $relationData) {
            $relation = $relationData["relation"] ?? null;
            $joinColumn = $relationData["joinColumn"] ?? null;

            if ($relation && $relation->fetch === FetchType::Lazy && $joinColumn !== null) {
                $alias = "{$metadata->getAlias()}_{$joinColumn->name}";
                $select["{$metadata->getAlias()}.{$joinColumn->name}"] ??= $alias;
            }
        }

        // Eager JOINs
        if ($resolveMetadata) {
            foreach ($metadata->getRelations() as $property => $relationData) {
                $relation = $relationData["relation"];

                if (
                    !$relation ||
                    !in_array($property, $eagerRelations, true) ||
                    $relation->fetch !== FetchType::Eager
                ) {
                    continue;
                }

                $relatedMetadata = $resolveMetadata($relation->entity);
                $joinAlias = $metadata->getRelationAlias($property);
                $joinTable = $relatedMetadata->getTable();
                $on = null;

                if (!empty($relation->mappedBy)) {
                    // Inverse side
                    $owningSide = $relatedMetadata->getRelations()[$relation->mappedBy];
                    $joinColumn = $owningSide["joinColumn"];

                    if ($joinColumn) {
                        $on = "{$joinAlias}.{$joinColumn->name} = {$metadata->getAlias()}.{$joinColumn->referencedColumn}";
                    }
                } else {
                    // Owning side
                    $joinColumn = $relationData["joinColumn"];
                    if ($joinColumn) {
                        $on = "{$metadata->getAlias()}.{$joinColumn->name} = {$joinAlias}.{$joinColumn->referencedColumn}";
                    }
                }

                if ($on) {
                    $query->leftJoin($joinTable, $joinAlias, $on);

                    foreach ($relatedMetadata->getColumns() as $column) {
                        $select["{$joinAlias}.{$column["name"]}"] = "{$joinAlias}_{$column["name"]}";
                    }
                }
            }
        }

        $query->select($select);

        // WHERE handling
        if (!is_null($conditions) && !is_array($conditions)) {
            $primaryKey = $metadata->getPrimaryKey();

            if (!isset($primaryKey)) {
                throw new InvalidArgumentException("Primary key does not exist");
            }

            $where["{$metadata->getAlias()}.{$primaryKey}"] = ":{$primaryKey}";
            $parameters[$primaryKey] = $conditions;
        } else {
            foreach ($conditions ?? [] as $key => $value) {
                $where["{$metadata->getAlias()}.{$key}"] = ":{$key}";
                $parameters[$key] = $value;
            }
        }

        $query->where($where, $parameters);
    }
}
