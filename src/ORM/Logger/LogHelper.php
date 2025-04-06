<?php

namespace ORM\Logger;

use Psr\Log\LoggerInterface;

/**
 * Helper class for logging SQL queries with context.
 *
 * This class provides a static utility method to log SQL statements,
 * typically used within the ORM to trace database operations.
 */
class LogHelper
{
    /**
     * Logs a SQL query and its parameters using the provided logger.
     *
     * This is useful for debugging and tracing what queries are executed during runtime.
     * The log level used is DEBUG.
     *
     * @param string $sql The raw SQL query string.
     * @param array $params An associative array of query parameters (e.g., [':id' => 123]).
     * @param LoggerInterface|null $logger A PSR-3 compatible logger instance (e.g., Monolog).
     *
     * @return void
     */
    public static function query(string $sql, array $params = [], ?LoggerInterface $logger = null): void
    {
        $logger?->debug('Executing SQL', [
            'sql' => $sql,
            'params' => $params,
        ]);
    }
}
