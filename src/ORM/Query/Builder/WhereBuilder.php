<?php

namespace ORM\Query\Builder;

use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryContext;

final class WhereBuilder
{
    /**
     * Builds the WHERE clause and SQL parameters based on normalized criteria.
     *
     * @param MetadataEntity $metadata
     * @param QueryContext $context
     * @param array $criteria Always normalized to associative array (key => value)
     *
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     */
    public function build(MetadataEntity $metadata, QueryContext $context, array $criteria): array
    {
        $where = [];
        $parameters = [];

        $prefix = $context->useAlias()
            ? $metadata->getAlias() . '.'
            : '';

        foreach ($criteria as $key => $value) {
            $where["{$prefix}{$key}"] = ":{$key}";
            $parameters[$key] = $value;
        }

        return [$where, $parameters];
    }
}
