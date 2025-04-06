<?php

namespace ORM\Drivers;

use InvalidArgumentException;
use RuntimeException;

/**
 * Interface DatabaseDriver
 *
 * Defines a common interface for all database drivers used in the ORM.
 * This abstraction allows the ORM to work independently of specific
 * database technologies (e.g., PDO, MySQLi, etc.).
 */
interface DatabaseDriver
{
    /**
     * Establishes a connection to the database.
     *
     * This method should be called before executing any queries.
     *
     * @throws RuntimeException If the connection attempt fails.
     */
    public function connect(): void;

    /**
     * Prepares an SQL statement and returns a driver-specific statement object.
     *
     * @param string $sql The SQL query to prepare.
     * @return Statement The wrapped statement object that implements the ORM's Statement interface.
     * @throws InvalidArgumentException If the provided SQL is invalid.
     */
    public function prepare(string $sql): Statement;

    /**
     * Returns the ID of the last inserted row.
     *
     * Some database drivers (e.g. PostgreSQL) may require the table and column
     * to be passed to retrieve the correct sequence.
     *
     * @param string|null $table Optional table name for databases that use sequences.
     * @param string|null $column Optional column name for databases that use sequences.
     * @return int|string The last insert ID as a string or integer.
     */
    public function lastInsertId(?string $table = null, ?string $column = null): int|string;

    /**
     * Quotes an identifier (e.g., table or column name) to prevent conflicts with reserved words
     * and to allow special characters.
     *
     * @param string $name The identifier name to quote.
     * @return string The quoted identifier.
     */
    public function quoteIdentifier(string $name): string;
}