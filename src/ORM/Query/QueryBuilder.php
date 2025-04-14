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
    protected ?string $action;
    protected ?string $table;
    protected array $values = [];
    protected array $columns  = [];
    protected array $where = [];
    protected array $joins = [];
    protected array $parameters = [];

    public function __construct(
        protected readonly DatabaseDriver $databaseDriver,
        protected readonly ?LoggerInterface $logger = null,
    ) {}

    public function fromMetadata(
        MetadataEntity $metadata,
        int|string|array|null $payload = null,
        ?callable $resolveMetadata = null,
        array $eagerRelations = []
    ): self {
        $this->table($metadata->getTable(), $metadata->getAlias());

        match ($this->action) {
            'select' => $this->fromMetadataSelect($metadata, $payload, $resolveMetadata, $eagerRelations),
            'insert' => $this->fromMetadataInsert($metadata, $payload),
            'update' => $this->fromMetadataUpdate($metadata, $payload),
            'delete' => $this->fromMetadataDelete($metadata, $payload),
            default => throw new RuntimeException("Unsupported action: {$this->action}")
        };

        return $this;
    }

    public function table(string $table, ?string $alias = null): self
    {
        $this->table = $this->databaseDriver->quoteIdentifier($table);

        if ($this->action === "select" && !empty($alias)) {
            $this->table .= " AS {$this->databaseDriver->quoteIdentifier($alias)}";
        }

        return $this;
    }

    public function select(?array $selectColumns = null): self
    {
        $this->action = "select";

        if (!empty($selectColumns)) {
            foreach ($selectColumns as $key => $alias) {
                if (is_int($key)) {
                    $this->columns[] = $this->databaseDriver->quoteIdentifier($alias);
                    continue;
                }

                [$table, $column] = explode('.', $key, 2) + [null, null];

                $quoted = $column === null
                    ? $this->databaseDriver->quoteIdentifier($table)
                    : $this->databaseDriver->quoteIdentifier($table) . '.' . $this->databaseDriver->quoteIdentifier($column);
                $quotedAlias = $this->databaseDriver->quoteIdentifier($alias);

                $this->columns[] = "{$quoted} AS {$quotedAlias}";
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
                $this->where["{$this->databaseDriver->quoteIdentifier($table)}"] = $value;
            } else {
                $this->where["{$this->databaseDriver->quoteIdentifier($table)}.{$this->databaseDriver->quoteIdentifier($column)}"] = $value;
            }
        }

        $this->parameters = $parameters;
        return $this;
    }

    public function leftJoin(string $table, string $alias, string $on): self
    {
        $this->joins[] = [
            'type' => 'LEFT',
            'table' => $this->databaseDriver->quoteIdentifier($table),
            'alias' => $this->databaseDriver->quoteIdentifier($alias),
            'on' => preg_replace_callback('/\b([\w]+)\.([\w]+)\b/', function ($matches) {
                return $this->databaseDriver->quoteIdentifier($matches[1]) . '.' . $this->databaseDriver->quoteIdentifier($matches[2]);
            }, $on),
        ];

        return $this;
    }

    public function values(array $data): self
    {
        $this->values = $data;
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

    public function execute(): int|Statement
    {
        try {
            $sql = $this->getSQL();

            $statement = $this->databaseDriver->prepare($sql);

            foreach ($this->parameters as $parameter => $value) {
                $statement->bindValue(":{$parameter}", $value);
            }

            LogHelper::query($sql, $this->parameters, $this->logger);
            $statement->execute();

            if ($this->action === "insert") {
                $lastInsertId = $this->databaseDriver->lastInsertId();
                $this->reset();

                return $lastInsertId;
            }

            $this->reset();
            return $statement;
        } catch (PDOException $e) {
            throw new RuntimeException("Query execution failed: {$e->getMessage()}", 0, $e);
        }
    }

    protected function reset(): void
    {
        $this->action = null;
        $this->table = null;
        $this->values = [];
        $this->columns = [];
        $this->where = [];
        $this->joins = [];
        $this->parameters = [];
    }

    protected function fromMetadataSelect(
        MetadataEntity $metadata,
        int|string|array|null $conditions = null,
        ?callable $resolveMetadata = null,
        array $eagerRelations = [],
    ): void {
        $select = [];
        $where = [];
        $parameters = [];

        foreach ($metadata->getColumns() as $column) {
            $select["{$metadata->getAlias()}.{$column["name"]}"] = "{$metadata->getColumnAlias($column["name"])}";
        }

        if ($resolveMetadata) {
            foreach ($metadata->getRelations() as $property => $relationData) {
                if (!in_array($property, $eagerRelations, true)) {
                    continue;
                }

                $relation = $relationData["relation"];
                if (!$relation) {
                    continue;
                }

                $relatedMetadata = $resolveMetadata($relation->entity);
                $joinAlias = $metadata->getRelationAlias($property);
                $joinTable = $relatedMetadata->getTable();
                $on = null;

                // Inverse side
                if (!empty($relation->mappedBy)) {
                    $owningSide = $relatedMetadata->getRelations()[$relation->mappedBy];
                    $joinColumn = $owningSide["joinColumn"];

                    if ($joinColumn) {
                        $on = "{$joinAlias}.{$joinColumn->name} = {$metadata->getAlias()}.{$joinColumn->referencedColumn}";
                    }

                // Owning side
                } else {
                    $joinColumn = $relationData["joinColumn"];

                    if ($joinColumn) {
                        $on = "{$metadata->getAlias()}.{$joinColumn->name} = {$joinAlias}.{$joinColumn->referencedColumn}";
                    }
                }

                if ($on) {
                    $this->leftJoin($joinTable, $joinAlias, $on);

                    foreach ($relatedMetadata->getColumns() as $column) {
                        $select["{$joinAlias}.{$column["name"]}"] = "{$joinAlias}_{$column["name"]}";
                    }
                }
            }
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

        $this->where($where, $parameters);
    }

    protected function fromMetadataInsert(MetadataEntity $metadata, array $data): void
    {
//        @todo: set default values if is not empty and property value is not set...
//        foreach ($metadata->getColumns() as $column) {
//            if (array_key_exists($column["name"], $data) && $data[$column["name"]] === null && $column["attributes"]->default !== null) {
//                $data[$column["name"]] = $column["attributes"]->default;
//            }
//        }

        $this->values($data);
    }

    protected function fromMetadataUpdate(MetadataEntity $metadata, array $data): void
    {
        $primaryKey = $metadata->getPrimaryKey();
        $primaryValue = $data[$primaryKey] ?? null;

        if ($primaryValue === null) {
            throw new InvalidArgumentException("Missing primary key value for update");
        }

        unset($data[$primaryKey]);

        $this->values($data);
        $this->where([$primaryKey => ':id'], ['id' => $primaryValue]);
    }

    protected function fromMetadataDelete(MetadataEntity $metadata, int|string $id): void
    {
        $this->where([$metadata->getPrimaryKey() => ':id'], ['id' => $id]);
    }

    /**
     * @return string
     *
     * @note
     * SELECT ...
     * FROM table [AS alias]
     * [JOIN ...]
     * [WHERE ...]
     * [GROUP BY ...]
     * [ORDER BY ...]
     */
    protected function getSelectSQL(): string
    {
        $sqlParts = ["SELECT " . implode(", ", $this->columns) . " FROM {$this->table}"];

        foreach ($this->joins as $join) {
            $sqlParts[] = "{$join["type"]} JOIN {$join["table"]} AS {$join["alias"]} ON {$join['on']}";
        }

        if (!empty($this->where)) {
            $whereParts = [];

            foreach ($this->where as $key => $value) {
                $whereParts[] = "$key = {$value}";
            }

            $sqlParts[] = "WHERE " . implode(" AND ", $whereParts);
        }

        return implode(" ", $sqlParts);
    }

    protected function getInsertSQL(): string
    {
        $columns = array_keys($this->values);
        $this->parameters = $this->values;

        return sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', array_map([$this->databaseDriver, 'quoteIdentifier'], $columns)),
            implode(', ', array_map(fn($col) => ":$col", $columns))
        );
    }

    protected function getUpdateSQL(): string
    {
        if (empty($this->values)) {
            throw new RuntimeException("No values set for update.");
        }

        $setParts = [];
        foreach ($this->values as $column => $_) {
            $quoted = $this->databaseDriver->quoteIdentifier($column);
            $setParts[] = "{$quoted} = :{$column}";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts);

        if (!empty($this->where)) {
            $whereParts = [];
            foreach ($this->where as $key => $value) {
                $whereParts[] = "$key = {$value}";
            }
            $sql .= " WHERE " . implode(" AND ", $whereParts);
        }

        $this->parameters = array_merge($this->values, $this->parameters);

        return $sql;
    }

    protected function getDeleteSQL(): string
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->where)) {
            $whereParts = [];
            foreach ($this->where as $key => $value) {
                $whereParts[] = "$key = {$value}";
            }

            $sql .= " WHERE " . implode(" AND ", $whereParts);
        }

        return $sql;
    }
}