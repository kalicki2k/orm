<?php

namespace ORM\Query\Builder;

use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryBuilder;

final class DeleteBuilder
{
    public function apply(QueryBuilder $query, MetadataEntity $metadata, int|string $id): void
    {
        $query->where(
            [$metadata->getPrimaryKey() => ':id'],
            ['id' => $id]
        );
    }
}
