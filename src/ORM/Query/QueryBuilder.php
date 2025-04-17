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
    private QueryContext $queryContext;

    public function __construct(
        private readonly DatabaseDriver $databaseDriver,
        private readonly ?LoggerInterface $logger = null,
        private readonly SelectBuilder $selectBuilder = new SelectBuilder(),
        private readonly InsertBuilder $insertBuilder = new InsertBuilder(),
        private readonly UpdateBuilder $updateBuilder = new UpdateBuilder(),
        private readonly DeleteBuilder $deleteBuilder = new DeleteBuilder(),
    ) {
        $this->queryContext = new QueryContext();
    }

    public function getContext(): QueryContext
    {
        return $this->queryContext;
    }

    public function getSQL(): string
    {
        return match ($this->queryContext->action) {
            "select" => new SelectSqlRenderer()->render($this->queryContext, $this->databaseDriver),
            "insert" => new InsertSqlRenderer()->render($this->queryContext, $this->databaseDriver),
            "update" => new UpdateSqlRenderer()->render($this->queryContext, $this->databaseDriver),
            "delete" => new DeleteSqlRenderer()->render($this->queryContext, $this->databaseDriver),
            default => new RuntimeException("Unknown action: {$this->queryContext->action}")
        };
    }

    public function fromMetadata(
        MetadataEntity $metadata,
        int|string|array|null $payload = null,
        ?callable $resolveMetadata = null,
        array $eagerRelations = []
    ): self {
        $this->table($metadata->getTable(), $metadata->getAlias());

        match ($this->queryContext->action) {
            'select' => $this->selectBuilder->apply($this, $metadata, $payload, $resolveMetadata, $eagerRelations),
            'insert' => $this->insertBuilder->apply($this, $metadata, $payload),
            'update' => $this->updateBuilder->apply($this, $metadata, $payload),
            'delete' => $this->deleteBuilder->apply($this, $metadata, $payload),
            default => throw new RuntimeException("Unsupported action: {$this->queryContext->action}")
        };

        return $this;
    }

    public function table(string $table, ?string $alias = null): self
    {
        $tableQuoted = $this->databaseDriver->quoteIdentifier($table);
        $this->queryContext->alias = $alias;

        $this->queryContext->table = $this->queryContext->action === 'select' && $alias
            ? "$tableQuoted AS " . $this->databaseDriver->quoteIdentifier($alias)
            : $tableQuoted;

        return $this;
    }

    public function select(?array $selectColumns = null): self
    {
        $this->queryContext->action = "select";

        if (!empty($selectColumns)) {
            foreach ($selectColumns as $key => $alias) {
                if (is_int($key)) {
                    $this->queryContext->columns[] = $this->databaseDriver->quoteIdentifier($alias);
                    continue;
                }

                [$table, $column] = explode('.', $key, 2) + [null, null];

                $quoted = $column === null
                    ? $this->databaseDriver->quoteIdentifier($table)
                    : $this->databaseDriver->quoteIdentifier($table) . '.' . $this->databaseDriver->quoteIdentifier($column);
                $quotedAlias = $this->databaseDriver->quoteIdentifier($alias);

                $this->queryContext->columns[] = "$quoted AS $quotedAlias";
            }
        }

        return $this;
    }

    public function insert(): self
    {
        $this->queryContext->action = "insert";
        return $this;
    }

    public function update(): self
    {
        $this->queryContext->action = "update";
        return $this;
    }

    public function delete(): self
    {
        $this->queryContext->action = "delete";
        return $this;
    }

    public function where(array $whereConditions, array $parameters): self
    {
        foreach ($whereConditions as $key => $value) {
            [$table, $column] = explode(".", "$key", 2) + [null, null];

            if (is_null($column)) {
                $this->queryContext->where["{$this->databaseDriver->quoteIdentifier($table)}"] = $value;
            } else {
                $this->queryContext->where["{$this->databaseDriver->quoteIdentifier($table)}.{$this->databaseDriver->quoteIdentifier($column)}"] = $value;
            }
        }

        $this->queryContext->parameters = $parameters;
        return $this;
    }

    public function leftJoin(string $table, string $alias, string $on): self
    {
        $this->queryContext->joins[] = [
            'type' => 'LEFT',
            'table' => $this->databaseDriver->quoteIdentifier($table),
            'alias' => $this->databaseDriver->quoteIdentifier($alias),
            'on' => preg_replace_callback('/\b(\w+)\.(\w+)\b/', function ($matches) {
                return $this->databaseDriver->quoteIdentifier($matches[1]) . '.' . $this->databaseDriver->quoteIdentifier($matches[2]);
            }, $on),
        ];

        return $this;
    }

    public function values(array $data): self
    {
        $this->queryContext->values = $data;
        return $this;
    }

    public function execute(): int|Statement
    {
        try {
            $sql = $this->getSQL();

            $statement = $this->databaseDriver->prepare($sql);

            foreach ($this->queryContext->parameters as $parameter => $value) {
                $statement->bindValue(":$parameter", $value);
            }

            LogHelper::query($sql, $this->queryContext->parameters, $this->logger);
            $statement->execute();

            if ($this->queryContext->action === "insert") {
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
        $this->queryContext->action = null;
        $this->queryContext->table = null;
        $this->queryContext->values = [];
        $this->queryContext->columns = [];
        $this->queryContext->where = [];
        $this->queryContext->joins = [];
        $this->queryContext->parameters = [];
    }
}