<?php

namespace ORM\Query\Sql;

use ORM\Drivers\DatabaseDriver;
use ORM\Query\QueryContext;

final class DeleteSqlRenderer implements SqlRenderer
{
    public function render(QueryContext $queryContext, DatabaseDriver $databaseDriver): string
    {
        $sql = "DELETE FROM $queryContext->table";

        if (!empty($queryContext->where)) {
            [$whereSql, $parameters] = $queryContext->where->compile();

            $sql .= " WHERE $whereSql";

            $queryContext->parameters = [...$queryContext->parameters, ...$parameters];
        }

        return $sql;
    }
}
