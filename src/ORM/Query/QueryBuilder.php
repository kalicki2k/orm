<?php

namespace ORM\Query;

use ORM\Drivers\DatabaseDriver;
use ORM\Drivers\Statement;
use PDOException;
use RuntimeException;

class QueryBuilder
{
    protected string $action = "select";
    protected string $table = "";
    protected array $selectColumns  = [];
    protected array $whereConditions = [];
    protected array $parameters = [];

    public function __construct(protected DatabaseDriver $databaseDriver) {}

    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(array $selectColumns): self
    {
        $this->action = "select";
        $this->selectColumns = $selectColumns;
        return $this;
    }

    public function where(array $whereConditions, array $parameters = []): self
    {
        $this->whereConditions = $whereConditions;
        $this->parameters = $parameters;

        return $this;
    }

    public function getSQL(): string
    {
        return match ($this->action) {
            "select" => $this->getSelectSQL(),
            "insert" => $this->getInsertSQL(),
            "update" => $this->getUpdateSQL(),
            "delete" => $this->getDeleteSQL(),
            default => new RuntimeException("Unknown action: {$this->action}")
        };
    }

    public function execute(): Statement
    {
        try {
            $sql = $this->getSQL();
            $statement = $this->databaseDriver->prepare($sql);

            foreach ($this->parameters as $parameter => $value) {
                $statement->bindValue(":{$parameter}", $value);
            }

            $statement->execute();
            return $statement;
        } catch (PDOException $e) {
            throw new RuntimeException("Query execution failed: {$e->getMessage()}", 0, $e);
        }
    }

    protected function getSelectSQL(): string
    {
        $sqlParts = [];

        foreach ($this->selectColumns as &$column) {
            $column = $this->databaseDriver->quoteIdentifier($column);
        }

        $columns = implode(", ", $this->selectColumns);

        $sqlParts[] = "SELECT {$columns} FROM {$this->databaseDriver->quoteIdentifier($this->table)}";

        if (!empty($this->whereConditions)) {
            $sqlParts[] = "WHERE";

            foreach ($this->whereConditions as $key => $value) {
                $sqlParts[] = "{$this->databaseDriver->quoteIdentifier($key)} = {$value}";
            }
        }

        return implode(" ", $sqlParts);
    }

    protected function getInsertSQL(): string
    {
        return "";
    }

    protected function getUpdateSQL(): string
    {
        return "";
    }

    protected function getDeleteSQL(): string
    {
        return "";
    }
}