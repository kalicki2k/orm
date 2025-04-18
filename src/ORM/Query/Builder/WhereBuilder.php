<?php

namespace ORM\Query\Builder;

use ORM\Metadata\MetadataEntity;
use ORM\Query\Expression;
use ORM\Query\QueryContext;

final class WhereBuilder
{
    /**
     * Builds the WHERE clause and SQL parameters based on normalized criteria.
     *
     * @param MetadataEntity $metadata
     * @param QueryContext $context
     * @param Expression|array $criteria
     *
     * @return array{0: array<string, string>, 1: array<string, mixed>}
     */
    public function build(
        MetadataEntity $metadata,
        QueryContext $context,
        Expression|array $criteria
    ): array
    {
        if ($criteria instanceof Expression) {
            return $criteria->compile();
        }

        $where = [];
        $parameters = [];

        $prefix = $context->useAlias()
            ? $metadata->getAlias() . '.'
            : '';

        foreach ($criteria as $key => $value) {
            $where["$prefix$key"] = ":$key";
            $parameters[$key] = $value;
        }

        return [$where, $parameters];
    }
}
