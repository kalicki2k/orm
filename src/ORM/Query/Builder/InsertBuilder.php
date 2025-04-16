<?php

namespace ORM\Query\Builder;

use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryBuilder;

final class InsertBuilder
{
    public function apply(QueryBuilder $query, MetadataEntity $metadata, array $data): void
    {
        $query->values($data);
    }
}
