<?php

namespace ORM\Query\Builder;

use InvalidArgumentException;
use ORM\Metadata\MetadataEntity;
use ORM\Query\QueryContext;

final class WhereBuilder
{
    /**
     * Builds the WHERE clause structure and SQL parameters.
     *
     * @param MetadataEntity $metadata
     * @param QueryContext $context
     * @param int|string|array|null $conditions
     *
     * @return array{where: array<string,string>, parameters: array<string,mixed>}
     */
    public function build(MetadataEntity $metadata, QueryContext $context, int|string|array|null $conditions = null): array
    {
        $where = [];
        $parameters = [];
        $primaryKey = $metadata->getPrimaryKey();

        $prefix = $context->useAlias()
            ? $metadata->getAlias() . '.'
            : '';

        if ($conditions === null) {
            return [[], []];
        }

        if (!is_array($conditions)) {
            $where["{$prefix}{$primaryKey}"] = ":{$primaryKey}";
            $parameters[$primaryKey] = $conditions;
            return [$where, $parameters];
        }

        foreach ($conditions as $key => $value) {
            $where["{$prefix}{$key}"] = ":{$key}";
            $parameters[$key] = $value;
        }

        return [$where, $parameters];
    }
}
