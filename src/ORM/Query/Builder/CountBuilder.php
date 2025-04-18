<?php

namespace ORM\Query\Builder;

use ORM\Metadata\MetadataEntity;
use ORM\Query\Expression;
use ORM\Query\QueryBuilder;

final class CountBuilder
{
    public function apply(
        QueryBuilder $queryBuilder,
        MetadataEntity $metadata,
        Expression|array|null $criteria = null,
        array $options = []
    ): void {
        $alias = $metadata->getAlias();
        $column = $options["distinct"] ?? false
            ? "DISTINCT $alias.{$metadata->getPrimaryKey()}"
            : "*";

        $queryBuilder
            ->select(["COUNT($column)" => "count"])
            ->table($metadata->getTable(), $metadata->getAlias());

        [$where, $parameters] = new WhereBuilder()->build($metadata, $queryBuilder->getContext(), $criteria);
        $queryBuilder->where($where, $parameters);
    }
}
