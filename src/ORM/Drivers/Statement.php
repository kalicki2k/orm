<?php

namespace ORM\Drivers;

/**
 * Interface Statement
 *
 * Defines a common interface for database statement objects used in the ORM.
 * This interface allows the ORM to work with different database drivers uniformly.
 */
interface Statement
{
    /**
     * Binds a value to a named parameter in the prepared SQL statement.
     *
     * This method is used to securely pass data into the SQL statement and
     * prevent SQL-Injection vulnerabilities.
     *
     * @param string $param The name of the parameter to bind (e.g., ':id').
     * @param mixed $value The value to bind to the parameter.
     */
    public function bindValue(string $param, mixed $value): void;

    /**
     * Executes the prepared SQL statement.
     *
     * @return bool Returns true on successful execution, or false on failure.
     */
    public function execute(): bool;

    /**
     * Fetches the next row from the result set.
     *
     * Typically, returns an associative array representing the row, or false
     * if there are no more rows.
     *
     * @return array|false The next row as an associative array, or false if the end is reached.
     */
    public function fetch(): array|false;

    /**
     * Fetches all remaining rows from the result set.
     *
     * @return array An array of rows, where each row is an associative array.
     */
    public function fetchAll(): array;
}
