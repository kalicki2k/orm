<?php

namespace ORM\Query;

use ORM\Drivers\DatabaseDriver;
use ORM\Drivers\Statement;
use ORM\Logger\LogHelper;
use ORM\Metadata\MetadataEntity;
use ORM\Query\Builder\DeleteBuilder;
use ORM\Query\Builder\InsertBuilder;
use ORM\Query\Builder\SelectBuilder;
use ORM\Query\Builder\UpdateBuilder;
use ORM\Query\Sql\DeleteSqlRenderer;
use ORM\Query\Sql\InsertSqlRenderer;
use ORM\Query\Sql\SelectSqlRenderer;
use ORM\Query\Sql\UpdateSqlRenderer;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

class QueryBuilder
{
    private ?string $action;
    private ?string $table;
    private array $values = [];
    private array $columns  = [];
    private array $where = [];
    private array $joins = [];
    private array $parameters = [];

    public function __construct(
        private readonly DatabaseDriver $databaseDriver,
        private readonly ?LoggerInterface $logger = null,
        private readonly SelectBuilder $selectBuilder = new SelectBuilder(),
        private readonly InsertBuilder $insertBuilder = new InsertBuilder(),
        private readonly UpdateBuilder $updateBuilder = new UpdateBuilder(),
        private readonly DeleteBuilder $deleteBuilder = new DeleteBuilder(),
    ) {}

    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    public function getDatabaseDriver(): DatabaseDriver
    {
        return $this->databaseDriver;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getJoins(): array
    {
        return $this->joins;
    }

    public function getWhere(): array
    {
        return $this->where;
    }

    public function getSQL(): string
    {
        return match ($this->action) {
            "select" => new SelectSqlRenderer()->render($this),
            "insert" => new InsertSqlRenderer()->render($this),
            "update" => new UpdateSqlRenderer()->render($this),
            "delete" => new DeleteSqlRenderer()->render($this),
            default => new RuntimeException("Unknown action: {$this->action}")
        };
    }

    public function fromMetadata(
        MetadataEntity $metadata,
        int|string|array|null $payload = null,
        ?callable $resolveMetadata = null,
        array $eagerRelations = []
    ): self {
        $this->table($metadata->getTable(), $metadata->getAlias());

        match ($this->action) {
            'select' => $this->selectBuilder->apply($this, $metadata, $payload, $resolveMetadata, $eagerRelations),
            'insert' => $this->insertBuilder->apply($this, $metadata, $payload),
            'update' => $this->updateBuilder->apply($this, $metadata, $payload),
            'delete' => $this->deleteBuilder->apply($this, $metadata, $payload),
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

    private function reset(): void
    {
        $this->action = null;
        $this->table = null;
        $this->values = [];
        $this->columns = [];
        $this->where = [];
        $this->joins = [];
        $this->parameters = [];
    }
}