<?php

namespace ORM\Query\Builder;

use InvalidArgumentException;
use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryBuilder;

final class UpdateBuilder
{
    public function apply(QueryBuilder $query, MetadataEntity $metadata, array $data): void
    {
        $primaryKey = $metadata->getPrimaryKey();
        $primaryValue = $data[$primaryKey] ?? null;

        if ($primaryValue === null) {
            throw new InvalidArgumentException("Missing primary key value for update");
        }

        unset($data[$primaryKey]);

        $query
            ->values($data)
            ->where([$primaryKey => ':id'], ['id' => $primaryValue]);
    }
}
