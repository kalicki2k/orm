<?php

namespace ORM\Query\Sql;

use ORM\Drivers\DatabaseDriver;
use ORM\Query\QueryContext;

final class InsertSqlRenderer implements SqlRenderer
{
    public function render(QueryContext $queryContext, DatabaseDriver $databaseDriver): string
    {
        $values = $queryContext->values;
        $columns = array_keys($values);
        $queryContext->parameters = $values;


        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $queryContext->table,
            implode(', ', array_map([$databaseDriver, 'quoteIdentifier'], $columns)),
            implode(', ', array_map(fn($col) => ":$col", $columns))
        );
    }
}
