<?php

namespace ORM\Query\Sql;

use ORM\Query\QueryBuilder;

final class InsertSqlRenderer implements SqlRenderer
{
    public function render(QueryBuilder $queryBuilder): string
    {
        $values = $queryBuilder->getValues();
        $columns = array_keys($values);
        $queryBuilder->setParameters($values);


        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $queryBuilder->getTable(),
            implode(', ', array_map([$queryBuilder->getDatabaseDriver(), 'quoteIdentifier'], $columns)),
            implode(', ', array_map(fn($col) => ":$col", $columns))
        );
    }
}
