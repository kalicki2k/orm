<?php

namespace ORM\Query\Sql;

use ORM\Drivers\DatabaseDriver;
use ORM\Query\QueryContext;
use RuntimeException;

final class UpdateSqlRenderer implements SqlRenderer
{
    public function render(QueryContext $queryContext, DatabaseDriver $databaseDriver): string
    {
        $values = $queryContext->values;

        if (empty($values)) {
            throw new RuntimeException("No values set for update.");
        }

        $setParts = [];
        foreach ($values as $column => $_) {
            $quoted = $databaseDriver->quoteIdentifier($column);
            $setParts[] = "$quoted = :$column";
        }

        $sql = "UPDATE $queryContext->table SET " . implode(", ", $setParts);

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
