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
//        array $eagerRelations = [],
        array $options = [],
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
            $eagerRelations = $options["relations"] ?? [];
            $this->joinBuilder->apply($queryBuilder, $metadata, $eagerRelations, $resolveMetadata);
        }

        [$where, $parameters] = $this->whereBuilder->build($metadata, $queryBuilder->getContext(), $conditions);
        $queryBuilder->where($where, $parameters);


        var_dump($options);
        $this->applyOptions($queryBuilder, $options);
    }

    private function applyOptions(QueryBuilder $queryBuilder, array $options): void
    {
        if (isset($options["limit"])) $queryBuilder->limit($options["limit"]);
        if (isset($options["offset"])) $queryBuilder->offset($options["offset"]);
        if (isset($options["orderBy"])) $queryBuilder->orderBy($options["orderBy"]);
        if (isset($options["groupBy"])) $queryBuilder->groupBy($options["groupBy"]);
        if (!empty($options["distinct"])) $queryBuilder->distinct();
    }
}
