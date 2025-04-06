<?php

namespace ORM\Drivers;

use PDO;
use PDOStatement;

/**
 * Class PDOStatementAdapter
 *
 * Implements the Statement interface by adapting a PDOStatement.
 * This adapter allows the ORM to work with PDOStatements in a uniform manner,
 * following the Adapter design pattern.
 *
 * @link https://refactoring.guru/design-patterns/adapter
 */
class PDOStatementAdapter implements Statement
{
    /**
     * PDOStatementAdapter constructor.
     *
     * @param PDOStatement $statement The PDOStatement instance to wrap.
     */
    public function __construct(private PDOStatement $statement) {}

    /**
     * Binds a value to a named parameter in the prepared statement.
     *
     * This method delegates the binding to the underlying PDOStatement.
     *
     * @param string $param The parameter identifier (e.g., ':name').
     * @param mixed $value The value to bind to the parameter.
     */
    public function bindValue(string $param, mixed $value): void
    {
        $this->statement->bindValue($param, $value);
    }

    /**
     * Executes the prepared statement.
     *
     * @return bool Returns true on success, or false on failure.
     */
    public function execute(): bool
    {
        return $this->statement->execute();
    }

    /**
     * Fetches the next row from the result set as an associative array.
     *
     * @return array|false Returns the next row as an associative array, or false if there are no more rows.
     */
    public function fetch(): array|false
    {
        return $this->statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Fetches all remaining rows from the result set as an array of associative arrays.
     *
     * @return array An array of rows, each row being an associative array.
     */
    public function fetchAll(): array
    {
        return $this->statement->fetchAll(PDO::FETCH_ASSOC);
    }
}