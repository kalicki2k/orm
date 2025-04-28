<?php

namespace ORM\Query\Sql;

use ORM\Drivers\DatabaseDriver;
use ORM\Query\Expression;
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
        $sqlParts = [];

        // SELECT + DISTINCT
        $selectClause = "SELECT";
        if ($queryContext->distinct) {
            $selectClause .= " DISTINCT";
        }

        $selectClause .= " " . implode(", ", $queryContext->columns);
        $sqlParts[] = $selectClause;

        // FROM
        $fromClause = $databaseDriver->quoteIdentifier($queryContext->table);

        if (!empty($queryContext->alias)) {
            $fromClause .= " AS " . $databaseDriver->quoteIdentifier($queryContext->alias);
        }

        $sqlParts[] = "FROM " . $fromClause;

        // JOINS
        foreach ($queryContext->joins as $join) {
            $sqlParts[] = sprintf(
                "%s JOIN %s AS %s ON %s",
                $join["type"],
                $databaseDriver->quoteIdentifier($join["table"]),
                $databaseDriver->quoteIdentifier($join["alias"]),
                preg_replace_callback("/\b(\w+)\.(\w+)\b/", function ($matches) use ($databaseDriver) {
                    return $databaseDriver->quoteIdentifier($matches[1]) . "." . $databaseDriver->quoteIdentifier($matches[2]);
                }, $join["on"])
            );
        }

        // WHERE
        if ($queryContext->where instanceof Expression) {
            [$whereSql, $params] = $queryContext->where->compile();
            $sqlParts[] = "WHERE " . $whereSql;
            $queryContext->parameters = array_merge($queryContext->parameters, $params);
        }

        // GROUP BY
        if (!empty($queryContext->groupBy)) {
            $groupBy = implode(", ", array_map([$databaseDriver, "quoteIdentifier"], $queryContext->groupBy));
            $sqlParts[] = "GROUP BY " . $groupBy;
        }

        // ORDER BY
        if (!empty($queryContext->orderBy)) {
            $orders = [];
            foreach ($queryContext->orderBy as $col => $dir) {
                $orders[] = $databaseDriver->quoteIdentifier($col) . " " . strtoupper($dir);
            }
            $sqlParts[] = "ORDER BY " . implode(", ", $orders);
        }

        // LIMIT & OFFSET
        if ($queryContext->limit !== null) {
            $sqlParts[] = "LIMIT " . (int) $queryContext->limit;
        }

        if ($queryContext->offset !== null) {
            $sqlParts[] = "OFFSET " . (int) $queryContext->offset;
        }

        return implode(" ", $sqlParts);
    }
}
