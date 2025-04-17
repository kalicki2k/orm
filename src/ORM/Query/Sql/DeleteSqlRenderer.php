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
            $whereParts = [];
            foreach ($queryContext->where as $key => $value) {
                $whereParts[] = "$key = $value";
            }

            $sql .= " WHERE " . implode(" AND ", $whereParts);
        }

        return $sql;
    }
}
