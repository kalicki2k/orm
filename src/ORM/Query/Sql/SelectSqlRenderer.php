<?php

namespace ORM\Query\Sql;

use ORM\Drivers\DatabaseDriver;
use ORM\Query\QueryContext;


final class SelectSqlRenderer implements SqlRenderer
{
    /**
     * Builds a complete SELECT SQL string.
     *
     * @param QueryContext $queryContext
     * @param DatabaseDriver $databaseDriver
     * @return string
     */
    public function render(QueryContext $queryContext, DatabaseDriver $databaseDriver): string
    {
        $sql = 'SELECT';

        // DISTINCT
        if ($queryContext->distinct) {
            $sql .= ' DISTINCT';
        }

        // COLUMNS
        $sql .= ' ' . implode(', ', $queryContext->columns);
        $sql .= " FROM {$queryContext->table}";

        // JOINS
        foreach ($queryContext->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} AS {$join['alias']} ON {$join['on']}";
        }

        // WHERE
        if (!empty($queryContext->where)) {
            $where = [];
            foreach ($queryContext->where as $col => $param) {
                $where[] = "$col = $param";
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // GROUP BY
        if (!empty($queryContext->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', array_map([$databaseDriver, 'quoteIdentifier'], $queryContext->groupBy));
        }

        // ORDER BY
        if (!empty($queryContext->orderBy)) {
            $order = [];
            foreach ($queryContext->orderBy as $col => $dir) {
                $order[] = $databaseDriver->quoteIdentifier($col) . ' ' . strtoupper($dir);
            }
            $sql .= ' ORDER BY ' . implode(', ', $order);
        }

        // LIMIT
        if ($queryContext->limit !== null) {
            $sql .= ' LIMIT ' . (int) $queryContext->limit;
        }

        // OFFSET
        if ($queryContext->offset !== null) {
            $sql .= ' OFFSET ' . (int) $queryContext->offset;
        }

        return $sql;
    }
}
