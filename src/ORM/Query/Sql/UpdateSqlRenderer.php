<?php

namespace ORM\Query\Sql;

use ORM\Drivers\DatabaseDriver;
use ORM\Query\Expression;
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

        if ($queryContext->where instanceof Expression) {
            [$whereSql] = $queryContext->where->compile();
            $sql .= " WHERE " . $whereSql;
        }

        return $sql;
    }
}
