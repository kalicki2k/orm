<?php

namespace ORM\Query\Sql;

use ORM\Drivers\DatabaseDriver;
use ORM\Query\QueryContext;

final class SelectSqlRenderer implements SqlRenderer
{
    /**
     * @param QueryContext $queryContext
     * @param DatabaseDriver $databaseDriver
     * @return string
     *
     * @note
     * SELECT ...
     * FROM table [AS alias]
     * [JOIN ...]
     * [WHERE ...]
     * [GROUP BY ...]
     * [ORDER BY ...]
     */
    public function render(QueryContext $queryContext, DatabaseDriver $databaseDriver): string
    {
        $columns = implode(", ", $queryContext->columns);
        $sqlParts = ["SELECT $columns FROM $queryContext->table"];

        foreach ($queryContext->joins as $join) {
            $sqlParts[] = "{$join["type"]} JOIN {$join["table"]} AS {$join["alias"]} ON {$join["on"]}";
        }

        if (!empty($queryContext->where)) {
            $whereParts = [];

            foreach ($queryContext->where as $key => $value) {
                $whereParts[] = "$key = $value";
            }

            $sqlParts[] = "WHERE " . implode(" AND ", $whereParts);
        }

        return implode(" ", $sqlParts);
    }
}
