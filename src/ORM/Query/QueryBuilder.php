<?php

namespace ORM\Query;

use InvalidArgumentException;
use ORM\Drivers\DatabaseDriver;
use ORM\Drivers\Statement;
use ORM\Logger\LogHelper;
use ORM\Metadata\MetadataEntity;
use ORM\Query\Builder\CountBuilder;
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
        private readonly CountBuilder $countBuilder = new CountBuilder(),
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
        array $options = [],
    ): self {
        $this->table($metadata->getTable(), $metadata->getAlias());

        match ($this->queryContext->action) {
            "count" => $this->countBuilder->apply($this, $metadata, $payload, $options),
            "select" => $this->selectBuilder->apply($this, $metadata, $payload, $resolveMetadata, $options),
            "insert" => $this->insertBuilder->apply($this, $metadata, $payload),
            "update" => $this->updateBuilder->apply($this, $metadata, $payload),
            "delete" => $this->deleteBuilder->apply($this, $metadata, $payload),
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

    public function count(): self
    {
        $this->queryContext->action = "count";
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

    /**
     * Sets the LIMIT for pagination.
     *
     * Limits the number of records returned by the query.
     *
     * @param int $limit The maximum number of records to return. Must be >= 0.
     *
     * @return $this
     *
     * @throws InvalidArgumentException if the limit is negative
     *
     * @example
     *   ->limit(10)
     */
    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException("Limit must be non-negative.");
        }

        $this->queryContext->limit = $limit;
        return $this;
    }

    /**
     * Sets the OFFSET for pagination.
     *
     * Skips the first N records before starting to return results.
     *
     * @param int $offset The number of records to skip. Must be >= 0.
     *
     * @return $this
     *
     * @throws InvalidArgumentException if the offset is negative
     *
     * @example
     *   ->offset(20)
     */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException("Offset must be non-negative.");
        }

        $this->queryContext->offset = $offset;
        return $this;
    }

    /**
     * Defines the ORDER BY clause for the query.
     *
     * Accepts either:
     * - a single column name (default direction ASC),
     * - an array of column names (all ASC),
     * - or an associative array with direction per column.
     *
     * @param string|array<int|string, string>|array<int, string> $orderBy
     *
     * @return $this
     *
     * @example
     *   ->orderBy("created_at")
     *   ->orderBy(["created_at" => "DESC", "username" => "ASC"])
     *   ->orderBy(["created_at", "username"]) // both ASC
     */
    public function orderBy(array|string $orderBy): self
    {
        if (is_string($orderBy)) {
            $orderBy = [$orderBy => 'ASC'];
        }

        $normalized = [];
        foreach ($orderBy as $key => $value) {
            if (is_int($key)) {
                $normalized[$value] = 'ASC';
            } else {
                $normalized[$key] = strtoupper($value) === 'DESC' ? 'DESC' : 'ASC';
            }
        }

        $this->queryContext->orderBy = $normalized;
        return $this;
    }

    /**
     * Defines the GROUP BY clause for the query.
     *
     * Accepts a single column or an array of columns.
     *
     * @param string|array<string> $groupBy Column name or list of column names
     *
     * @return $this
     *
     * @example
     *   ->groupBy("status")
     *   ->groupBy(["status", "type"])
     */
    public function groupBy(array|string $groupBy): self
    {
        $this->queryContext->groupBy = is_array($groupBy) ? $groupBy : [$groupBy];
        return $this;
    }

    /**
     * Enables or disables the DISTINCT flag for the query.
     *
     * @param bool $flag Whether to use DISTINCT in the SELECT clause.
     *
     * @return $this
     */
    public function distinct(bool $flag = true): self
    {
        $this->queryContext->distinct = $flag;
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