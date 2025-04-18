<?php

namespace ORM\Query\Builder;

use InvalidArgumentException;
use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryBuilder;

final readonly class UpdateBuilder
{
    public function __construct(
        private WhereBuilder $whereBuilder = new WhereBuilder()
    ) {}

    public function apply(QueryBuilder $queryBuilder, MetadataEntity $metadata, array $data): void
    {
        $primaryKey = $metadata->getPrimaryKey();
        $primaryValue = $data[$primaryKey] ?? null;

        if ($primaryValue === null) {
            throw new InvalidArgumentException("Missing primary key value for update");
        }

        unset($data[$primaryKey]);
        $queryBuilder->values($data);
        [$where, $params] = $this->whereBuilder->build($metadata, $queryBuilder->getContext(), [$primaryKey => $primaryValue]);
        $queryBuilder->where($where, $params);
    }
}
