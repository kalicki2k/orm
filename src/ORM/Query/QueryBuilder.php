<?php

namespace ORM\Query;

use InvalidArgumentException;
use ORM\Drivers\DatabaseDriver;
use ORM\Drivers\Statement;
use ORM\Logger\LogHelper;
use ORM\Metadata\MetadataEntity;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class QueryBuilder
{
    protected string $action = "select";
    protected string $table = "";
    protected array $selectColumns  = [];
    protected array $whereConditions = [];
    protected array $parameters = [];

    public function __construct(
        protected readonly DatabaseDriver $databaseDriver,
        protected readonly ?LoggerInterface $logger = null,
    ) {}

    public function fromMetadata(MetadataEntity $metadata, int|string|array|null $conditions = null): self
    {
        $this->table($metadata->getTable(), $metadata->getAlias());

        if ($this->action === "select") {
            $select = [];
            $where = [];
            $parameters = [];

            foreach ($metadata->getColumns() as $column) {
                $select["{$metadata->getAlias()}.{$column["name"]}"] = "{$metadata->getColumnAlias($column["name"])}";
            }
            $this->select($select);

            if (!is_null($conditions) && !is_array($conditions)) {
                $primaryKey = $metadata->getPrimaryKey();

                if (!isset($primaryKey)) {
                    throw new InvalidArgumentException("Primary key does not exist");
                }

                $where["{$metadata->getAlias()}.{$primaryKey}"] = ":{$primaryKey}";
                $parameters[$primaryKey] = $conditions;
            } else {
                foreach ($conditions as $key => $value) {
                    $where["{$metadata->getAlias()}.{$key}"] = ":{$key}";
                    $parameters[$key] = $value;
                }
            }

            $this
                ->where($where, $parameters);
        }

        return $this;
    }

    public function table(string $table, ?string $alias = null): self
    {
        $this->table = $this->databaseDriver->quoteIdentifier($table);

        if (!empty($alias)) {
            $this->table .= " AS {$this->databaseDriver->quoteIdentifier($alias)}";
        }

        return $this;
    }

    public function select(?array $selectColumns = null): self
    {
        $this->action = "select";

        if (!empty($selectColumns)) {
            foreach ($selectColumns as $key => $value) {
                if (is_int($key)) {
                    $this->selectColumns[] = "{$this->databaseDriver->quoteIdentifier($value)}";
                } else {
                    [$table, $column] = explode(".", "$key", 2) + [null, null];

                    if (is_null($column)) {
                        $this->selectColumns[] = "{$this->databaseDriver->quoteIdentifier($table)} AS {$this->databaseDriver->quoteIdentifier($value)}";
                    } else {
                        $this->selectColumns[] = "{$this->databaseDriver->quoteIdentifier($table)}.{$this->databaseDriver->quoteIdentifier($column)} AS {$this->databaseDriver->quoteIdentifier($value)}";
                    }
                }
            }
        }

        return $this;
    }

    public function insert(): self
    {
        $this->action = "insert";
        return $this;
    }

    public function update(): self
    {
        $this->action = "update";
        return $this;
    }

    public function delete(): self
    {
        $this->action = "delete";
        return $this;
    }

    public function where(array $whereConditions, array $parameters): self
    {
        foreach ($whereConditions as $key => $value) {
            [$table, $column] = explode(".", "$key", 2) + [null, null];

            if (is_null($column)) {
                $this->whereConditions["{$this->databaseDriver->quoteIdentifier($table)}"] = $value;
            } else {
                $this->whereConditions["{$this->databaseDriver->quoteIdentifier($table)}.{$this->databaseDriver->quoteIdentifier($column)}"] = $value;
            }
        }

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

            LogHelper::query($sql, $this->parameters, $this->logger);
            $statement->execute();
            return $statement;
        } catch (PDOException $e) {
            throw new RuntimeException("Query execution failed: {$e->getMessage()}", 0, $e);
        }
    }

    protected function getSelectSQL(): string
    {
        $sqlParts = ["SELECT " . implode(", ", $this->selectColumns) . " FROM {$this->table}"];

        if (!empty($this->whereConditions)) {
            $whereParts = [];

            foreach ($this->whereConditions as $key => $value) {
                $whereParts[] = "$key = {$value}";
            }

            $sqlParts[] = "WHERE " . implode(" AND ", $whereParts);
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