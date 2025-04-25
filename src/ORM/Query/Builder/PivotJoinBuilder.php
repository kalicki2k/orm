<?php

namespace ORM\Query\Builder;

use ORM\Query\Expression;
use ORM\Query\QueryBuilder;

final class PivotJoinBuilder
{
    public function apply(
        QueryBuilder $builder,
        string $targetTable,
        string $pivotTable,
        string $inverseJoinColumn,
        string $owningJoinColumn,
        int|string $parentId
    ): void {
        // Baue INNER JOIN auf Pivot-Tabelle
        $builder->innerJoin(
            $pivotTable,
            "{$pivotTable}",
            "{$pivotTable}.{$inverseJoinColumn} = {$targetTable}.id"
        );

        // Setze WHERE-Bedingung auf owningJoinColumn = parentId
        $builder->where(
            Expression::eq("{$pivotTable}.{$owningJoinColumn}", $parentId)
        );
    }
}
