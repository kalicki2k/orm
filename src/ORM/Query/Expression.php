<?php

namespace ORM\Query;

/**
 * Fluent expression builder for WHERE clauses.
 *
 * @example
 *  Expression::and()
 *      ->andEq("status", "active")
 *      ->orLike("email", "%@example.com")
 *      ->andBetween("age", 18, 65);
 */
final class Expression
{
    /**
     * Array of expressions with glue and params.
     *
     * @var array<array{sql: string, params: array<string, mixed>, glue: string}>
     */
    private array $expressions = [];

    /**
     * Starts a new AND expression block.
     */
    public static function and(): self
    {
        return new self();
    }

    /**
     * Starts a new OR expression block.
     */
    public static function or(): self
    {
        return new self();
    }

    /**
     * Starts an AND block with a single = condition.
     *
     * @param string $column
     * @param mixed $value
     *
     * @return self
     */
    public static function eq(string $column, mixed $value): self
    {
        return self::and()->andEq($column, $value);
    }

    /**
     * Adds a = condition using AND.
     *
     * @param string $column
     * @param mixed $value
     *
     * @return self
     */
    public function andEq(string $column, mixed $value): self
    {
        return $this->add("$column = :$column", [$column => $value]);
    }

    /**
     * Adds a = condition using OR.
     *
     * @param string $column
     * @param mixed $value
     *
     * @return self
     */
    public function orEq(string $column, mixed $value): self
    {
        return $this->add("$column = :$column", [$column => $value], "OR");
    }

    /**
     * Adds a > condition using AND.
     *
     * @param string $column
     * @param mixed $value
     *
     * @return self
     */
    public function andGt(string $column, mixed $value): self
    {
        return $this->add("$column > :$column", [$column => $value]);
    }

    /**
     * Adds a > condition using OR.
     *
     * @param string $column
     * @param mixed $value
     *
     * @return self
     */
    public function orGt(string $column, mixed $value): self
    {
        return $this->add("$column > :$column", [$column => $value], "OR");
    }

    /**
     * Adds a LIKE condition using AND.
     *
     * @param string $column
     * @param string $pattern
     *
     * @return self
     */
    public function andLike(string $column, string $pattern): self
    {
        return $this->add("$column LIKE :$column", [$column => $pattern]);
    }

    /**
     * Adds a LIKE condition using OR.
     *
     * @param string $column
     * @param string $pattern
     *
     * @return self
     */
    public function orLike(string $column, string $pattern): self
    {
        return $this->add("$column LIKE :$column", [$column => $pattern], "OR");
    }

    /**
     * Adds `column NOT LIKE :column` with AND glue.
     *
     * @param string $column
     * @param string $pattern
     *
     * @return self
     */
    public function andNotLike(string $column, string $pattern): self
    {
        return $this->add("$column NOT LIKE :$column", [$column => $pattern]);
    }

    /**
     * Adds `column NOT LIKE :column` with OR glue.
     *
     * @param string $column
     * @param string $pattern
     *
     * @return self
     */
    public function orNotLike(string $column, string $pattern): self
    {
        return $this->add("$column NOT LIKE :$column", [$column => $pattern], "OR");
    }

    /**
     * Adds `column BETWEEN :col_min AND :col_max` with AND glue.
     *
     * @param string $column
     * @param mixed $min
     * @param mixed $max
     *
     * @return self
     */
    public function andBetween(string $column, mixed $min, mixed $max): self
    {
        return $this->add("$column BETWEEN :{$column}_min AND :{$column}_max", [
            "{$column}_min" => $min,
            "{$column}_max" => $max,
        ]);
    }

    /**
     * Adds `column BETWEEN :col_min AND :col_max` with OR glue.
     *
     * @param string $column
     * @param mixed $min
     * @param mixed $max
     *
     * @return self
     */
    public function orBetween(string $column, mixed $min, mixed $max): self
    {
        return $this->add("$column BETWEEN :{$column}_min AND :{$column}_max", [
            "{$column}_min" => $min,
            "{$column}_max" => $max,
        ], "OR");
    }

    /**
     * Adds `column IN (...)` with AND glue.
     *
     * @param string $column
     * @param array $values
     *
     * @return self
     */
    public function andIn(string $column, array $values): self
    {
        return $this->buildInClause($column, $values);
    }

    /**
     * Adds `column IN (...)` with OR glue.
     *
     * @param string $column
     * @param array $values
     * @return self
     */
    public function orIn(string $column, array $values): self
    {
        return $this->buildInClause($column, $values, "IN", "OR");
    }

    /**
     * Adds `column NOT IN (...)` with AND glue.
     *
     * @param string $column
     * @param array $values
     * @return self
     */
    public function andNotIn(string $column, array $values): self
    {
        return $this->buildInClause($column, $values, "NOT IN");
    }

    /**
     * Adds `column NOT IN (...)` with OR glue.
     *
     * @param string $column
     * @param array $values
     * @return self
     */
    public function orNotIn(string $column, array $values): self
    {
        return $this->buildInClause($column, $values, "NOT IN", "OR");
    }

    /**
     * Adds `column IS NULL`.
     *
     * @param string $column
     * @param string $glue
     * @return self
     */
    public function isNull(string $column, string $glue = "AND"): self
    {
        return $this->add("$column IS NULL", [], $glue);
    }

    /**
     * Adds `column IS NOT NULL`.
     *
     * @param string $column
     * @param string $glue
     * @return self
     */
    public function isNotNull(string $column, string $glue = "AND"): self
    {
        return $this->add("$column IS NOT NULL", [], $glue);
    }

    /**
     * Adds a custom comparison condition.
     *
     * @param string $operator
     * @param string $column
     * @param mixed $value
     * @param string $glue

     * @return self
     *
     * @example ->where("!=", "type", "admin")
     */
    public function where(string $operator, string $column, mixed $value, string $glue = "AND"): self
    {
        return $this->add("$column $operator :$column", [$column => $value], $glue);
    }

    /**
     * @param string $sql
     * @param array $params
     * @param string $glue
     *
     * @return self
     */
    private function add(string $sql, array $params, string $glue = "AND"): self
    {
        $this->expressions[] = ["sql" => $sql, "params" => $params, "glue" => $glue];
        return $this;
    }

    /**
     * @param string $column
     * @param array $values
     * @param string $type
     * @param string $glue
     *
     * @return self
     */
    private function buildInClause(string $column, array $values, string $type = "IN", string $glue = "AND"): self
    {
        $placeholders = [];
        $params = [];

        foreach ($values as $i => $value) {
            $param = "{$column}_$i";
            $placeholders[] = ":$param";
            $params[$param] = $value;
        }

        $sql = "$column $type (" . implode(", ", $placeholders) . ")";
        return $this->add($sql, $params, $glue);
    }

    /**
     * Compiles the expression into a SQL fragment and parameters.
     *
     * @return array{0: array<int, string>, 1: array<string, mixed>}
     */
    public function compile(): array
    {
        $sql = [];
        $params = [];

        foreach ($this->expressions as $i => $expr) {
            if ($i > 0) {
                $sql[] = $expr["glue"];
            }
            $sql[] = "({$expr["sql"]})";
            $params = array_merge($params, $expr["params"]);
        }

        return [
            ["(" . implode(" ", $sql) . ")"],
            $params
        ];
    }
}
