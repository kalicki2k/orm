<?php

namespace ORM\Drivers;

use ORM\Config\DatabaseConfig;
use PDO;
use PDOException;

/**
 * Class PDODriver
 *
 * A PDO-based implementation of the DatabaseDriver interface.
 * This class encapsulates the logic to connect to a database,
 * prepare SQL statements, retrieve the last inserted ID,
 * and quote identifiers.
 */
class PDODriver implements DatabaseDriver
{
    /**
     * @var PDO|null The PDO instance representing the database connection.
     */
    private ?PDO $pdo;

    /**
     * PDODriver constructor.
     *
     * @param string $dsn The Data Source Name (e.g., "sqlite::memory:" or "mysql:host=localhost;dbname=test").
     * @param string|null $user The username for the database connection.
     * @param string|null $password The password for the database connection.
     * @param array $options Additional options for the PDO connection.
     */
    public function __construct(
        private string $dsn,
        private ?string $user = null,
        private ?string $password = null,
        private array $options = [],
    ) {}

    public static function default(): self
    {
        return self::fromConfig(DatabaseConfig::fromEnv());
    }

    public static function fromConfig(DatabaseConfig $config): self
    {
        $driver = new self($config->dsn, $config->username, $config->password);
        $driver->connect();
        return $driver;
    }

    /**
     * Establishes a connection to the database using PDO.
     *
     * This method initializes the PDO instance and sets the error mode to exceptions.
     *
     * @throws PDOException If the connection fails.
     */
    public function connect(): void
    {
        $this->pdo = new PDO($this->dsn, $this->user, $this->password, $this->options);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Prepares an SQL statement for execution.
     *
     * This method uses PDO to prepare the SQL query and wraps the resulting PDOStatement
     * in a PDOStatementAdapter, which implements the Statement interface.
     *
     * @param string $sql The SQL query to prepare.
     * @return Statement The prepared statement wrapped in a PDOStatementAdapter.
     * @throws PDOException If preparing the statement fails.
     */
    public function prepare(string $sql): Statement
    {
        return new PDOStatementAdapter($this->pdo->prepare($sql));
    }

    /**
     * Retrieves the ID of the last inserted row.
     *
     * Note: For some databases (e.g., PostgreSQL), you might need to pass the table
     * and column name to retrieve the correct sequence value. In this implementation,
     * these parameters are ignored.
     *
     * @param string|null $table Optional table name for databases that use sequences.
     * @param string|null $column Optional column name for databases that use sequences.
     * @return int|string The last inserted ID as an integer or string.
     * @throws PDOException If the retrieval of the last insert ID fails.
     */
    public function lastInsertId(?string $table = null, ?string $column = null): int|string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Quotes an identifier (e.g., a table or column name) to prevent conflicts with reserved words.
     *
     * This method wraps the provided identifier in backticks.
     *
     * @param string $name The identifier name to quote.
     * @return string The quoted identifier.
     */
    public function quoteIdentifier(string $name): string
    {
        // If it looks like a SQL function call or *
        if (
            $name === '*' ||
            str_contains($name, '(') || // function()
            str_contains($name, '.') || // table.column
            preg_match('/^\s*[A-Z]+\(/', $name) // e.g. COUNT(
        ) {
            return $name;
        }

        return "`$name`";
    }
}