<?php

namespace ORM\Query\Builder;

use InvalidArgumentException;
use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryBuilder;

final readonly class DeleteBuilder
{
    public function __construct(
        private WhereBuilder $whereBuilder = new WhereBuilder(),
    ) {}

    public function apply(QueryBuilder $queryBuilder, MetadataEntity $metadata, int|string|array|null $id): void
    {
        if ($id === null) {
            throw new InvalidArgumentException("Missing identifier for delete");
        }

//        $queryBuilder->where(
//            [$metadata->getPrimaryKey() => ':id'],
//            ['id' => $id]
//        );

        [$where, $params] = $this->whereBuilder->build($metadata, $queryBuilder->getContext(), $id);
        $queryBuilder->where($where, $params);
    }
}
