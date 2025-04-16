<?php

namespace ORM\Query\Sql;

use ORM\Query\QueryBuilder;

final class SelectSqlRenderer implements SqlRenderer
{
    /**
     * @param QueryBuilder $queryBuilder
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
    public function render(QueryBuilder $queryBuilder): string
    {
        $columns = implode(", ", $queryBuilder->getColumns());
        $sqlParts = ["SELECT {$columns} FROM {$queryBuilder->getTable()}"];

        foreach ($queryBuilder->getJoins() as $join) {
            $sqlParts[] = "{$join["type"]} JOIN {$join["table"]} AS {$join["alias"]} ON {$join["on"]}";
        }

        if (!empty($queryBuilder->getWhere())) {
            $whereParts = [];

            foreach ($queryBuilder->getWhere() as $key => $value) {
                $whereParts[] = "$key = {$value}";
            }

            $sqlParts[] = "WHERE " . implode(" AND ", $whereParts);
        }

        return implode(" ", $sqlParts);
    }
}
