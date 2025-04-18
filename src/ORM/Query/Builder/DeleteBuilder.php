<?php

namespace ORM\Query\Builder;


use ORM\Metadata\MetadataEntity;
use ORM\Query\Expression;
use ORM\Query\QueryBuilder;

final readonly class DeleteBuilder
{
    public function __construct(
        private WhereBuilder $whereBuilder = new WhereBuilder(),
    ) {}

    public function apply(QueryBuilder $queryBuilder, MetadataEntity $metadata, Expression|array $criteria): void
    {
        [$where, $params] = $this->whereBuilder->build($metadata, $queryBuilder->getContext(), $criteria);
        $queryBuilder->where($where, $params);
    }
}
