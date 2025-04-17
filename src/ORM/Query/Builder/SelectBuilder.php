<?php

namespace ORM\Query\Builder;

use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryBuilder;

final readonly class SelectBuilder
{
    public function __construct(
        private JoinBuilder $joinBuilder = new JoinBuilder(),
        private WhereBuilder $whereBuilder = new WhereBuilder()
    ) {}

    public function apply(
        QueryBuilder $queryBuilder,
        MetadataEntity $metadata,
        int|string|array|null $conditions = null,
        ?callable $resolveMetadata = null,
        array $eagerRelations = [],
    ): void {
        $select = [];
        foreach ($metadata->getColumns() as $column) {
            $select["{$metadata->getAlias()}.{$column["name"]}"] = "{$metadata->getColumnAlias($column["name"])}";
        }

        // Lazy foreign keys
        foreach ($metadata->getRelations() as $relationData) {
            $relation = $relationData["relation"] ?? null;
            $joinColumn = $relationData["joinColumn"] ?? null;

            if ($relation && $relation->fetch === FetchType::Lazy && $joinColumn !== null) {
                $alias = "{$metadata->getAlias()}_$joinColumn->name";
                $select["{$metadata->getAlias()}.$joinColumn->name"] ??= $alias;
            }
        }

        $queryBuilder->select($select);

        if ($resolveMetadata) {
            $this->joinBuilder->apply($queryBuilder, $metadata, $eagerRelations, $resolveMetadata);
        }

        [$where, $parameters] = $this->whereBuilder->build($metadata, $queryBuilder->getContext(), $conditions);
        $queryBuilder->where($where, $parameters);
    }
}
