<?php

namespace ORM\Query;

use InvalidArgumentException;
use ORM\Drivers\DatabaseDriver;
use ORM\Drivers\Statement;
use ORM\Logger\LogHelper;
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
            default => throw new RuntimeException("Unknown action: {$this->queryContext->action}")
        };
    }

    public function table(string $table, ?string $alias = null): self
    {
        $this->queryContext->table = $table;
        $this->queryContext->alias = $alias;

        return $this;
    }

    public function select(array $selectColumns): self
    {
        $this->queryContext->action = "select";

        foreach ($selectColumns as $key => $alias) {
            $this->queryContext->columns[] = is_int($key)
                ? $alias
                : sprintf("%s AS %s",
                    $key,
                    $this->databaseDriver->quoteIdentifier($alias),
                );
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

    public function where(Expression $expression): self
    {
        $this->queryContext->where = $expression;
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

    public function join(string $type, string $table, string $alias, string $on): self
    {
        $this->queryContext->joins[] = [
            "type" => strtoupper($type),
            "table" => $table,
            "alias" => $alias,
            "on"=> $on,
        ];

        return $this;
    }

    public function leftJoin(string $table, string $alias, string $on): self
    {
        return $this->join("LEFT", $table, $alias, $on);
    }

    public function innerJoin(string $table, string $alias, string $on): self
    {
        return $this->join("INNER", $table, $alias, $on);
    }

    public function rightJoin(string $table, string $alias, string $on): self
    {
        return $this->join("RIGHT", $table, $alias, $on);
    }

    public function values(array $data): self
    {
        $this->queryContext->values = $data;
        $this->queryContext->parameters = array_merge($this->queryContext->parameters, $data);

        return $this;
    }

    public function execute(): int|Statement
    {
        try {
            $sql = $this->getSQL();

            $statement = $this->databaseDriver->prepare($sql);

            foreach ($this->queryContext->parameters as $parameter => $value) {
                $statement->bindValue(str_starts_with($parameter, ':') ? $parameter : ":$parameter", $value);
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
        $this->queryContext->alias = null;
        $this->queryContext->values = [];
        $this->queryContext->columns = [];
        $this->queryContext->where = null;
        $this->queryContext->joins = [];
        $this->queryContext->parameters = [];
        $this->queryContext->limit = null;
        $this->queryContext->offset = null;
        $this->queryContext->orderBy = [];
        $this->queryContext->groupBy = [];
        $this->queryContext->distinct = false;
    }
}