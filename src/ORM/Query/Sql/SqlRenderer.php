<?php

namespace ORM\Query\Sql;

use ORM\Drivers\DatabaseDriver;
use ORM\Query\QueryContext;

/**
 * Defines the contract for SQL string generation based on a query context.
 *
 * Implementations of this interface convert a prepared {@see QueryContext}
 * into a concrete SQL string for one of the CRUD operations (SELECT, INSERT, etc).
 *
 * Each renderer is responsible for:
 * - reading structured query metadata
 * - quoting identifiers using the active database driver
 * - generating portable SQL strings
 *
 * @see \ORM\Query\QueryBuilder::getSQL()
 */
interface SqlRenderer
{
    /**
     * Renders the final SQL string based on the current query context.
     *
     * @param QueryContext $queryContext The active query data (table, joins, where, values, etc.)
     * @param DatabaseDriver $databaseDriver Driver used for quoting and dialect support
     *
     * @return string Rendered SQL string ready for execution
     */
    public function render(QueryContext $queryContext, DatabaseDriver $databaseDriver): string;
}
