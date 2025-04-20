<?php

namespace ORM\Query\Builder;

use ORM\Metadata\MetadataEntity;
use ORM\Query\Expression;
use ORM\Query\QueryBuilder;

/**
 * Builds the SELECT clause of an ORM-based SQL query.
 *
 * This class extracts column definitions from entity metadata and constructs
 * the SELECT mappings using table and column aliases. It also delegates
 * JOIN-building and WHERE clause building to their respective builders.
 *
 * @example
 * ```php
 * $queryBuilder = new QueryBuilder(...);
 * $selectBuilder = new SelectBuilder();
 *
 * $selectBuilder->apply(
 *     $queryBuilder,
 *     $metadata,
 *     $resolveMetadata,
 *     ['id' => 1],
 *     ['joins' => ['profile']]
 * );
 * ```
 *
 * @see JoinBuilder
 * @see WhereBuilder
 * @see MetadataEntity
 */
final readonly class SelectBuilder
{
    public function __construct(
        private JoinBuilder $joinBuilder = new JoinBuilder(),
        private WhereBuilder $whereBuilder = new WhereBuilder()
    ) {}

    /**
     * Applies SELECT logic to the given QueryBuilder.
     *
     * @param QueryBuilder $queryBuilder The query being built.
     * @param MetadataEntity $metadata The metadata of the root entity.
     * @param callable|null $resolveMetadata Optional resolver for related entity metadata.
     * @param Expression|array|null $criteria WHERE conditions (as Expression or array).
     * @param array $options Optional modifiers: joins[], limit, offset, orderBy, groupBy, distinct
     */
    public function apply(
        QueryBuilder $queryBuilder,
        MetadataEntity $metadata,
        ?callable $resolveMetadata = null,
        Expression|array|null $criteria = null,
        array $options = [],
    ): void {
        $select = [];
        foreach ($metadata->getColumns() as $column) {
            $select["{$metadata->getAlias()}.{$column["name"]}"] = "{$metadata->getColumnAlias($column["name"])}";
        }

        $queryBuilder->select($select);

        if ($resolveMetadata) {
            $joins = $options["joins"] ?? [];
            $this->joinBuilder->apply($queryBuilder, $metadata, $joins, $resolveMetadata);
        }

        [$where, $parameters] = $this->whereBuilder->build($metadata, $queryBuilder->getContext(), $criteria);
        $queryBuilder->where($where, $parameters);
        $this->applyOptions($queryBuilder, $options);
    }

    /**
     * Applies optional query modifiers like limit, offset, order, groupBy.
     *
     * @param QueryBuilder $queryBuilder
     * @param array $options
     */
    private function applyOptions(QueryBuilder $queryBuilder, array $options): void
    {
        if (isset($options["limit"])) $queryBuilder->limit($options["limit"]);
        if (isset($options["offset"])) $queryBuilder->offset($options["offset"]);
        if (isset($options["orderBy"])) $queryBuilder->orderBy($options["orderBy"]);
        if (isset($options["groupBy"])) $queryBuilder->groupBy($options["groupBy"]);
        if (!empty($options["distinct"])) $queryBuilder->distinct();
    }
}
