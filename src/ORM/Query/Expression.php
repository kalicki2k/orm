<?php

namespace ORM\Query;

final class Expression
{
    private array $expressions = [];
    private string $glue;

    private function __construct(string $glue = "AND")
    {
        $this->glue = $glue;
    }

    public static function and(): self
    {
        return new self("AND");
    }

    public static function or(): self
    {
        return new self("OR");
    }

    public static function eq(string $column, mixed $value): self
    {
        return self::and()->andEq($column, $value);
    }

    public function andEq(string $column, mixed $value): self
    {
        return $this->add("$column = :$column", [$column => $value]);
    }

    public function orEq(string $column, mixed $value): self
    {
        return $this->add("$column = :$column", [$column => $value], "OR");
    }

    public function andGt(string $column, mixed $value): self
    {
        return $this->add("$column > :$column", [$column => $value]);
    }

    public function orGt(string $column, mixed $value): self
    {
        return $this->add("$column > :$column", [$column => $value], "OR");
    }

    public function andLike(string $column, string $pattern): self
    {
        return $this->add("$column LIKE :$column", [$column => $pattern]);
    }

    public function orLike(string $column, string $pattern): self
    {
        return $this->add("$column LIKE :$column", [$column => $pattern], "OR");
    }

    public function andNotLike(string $column, string $pattern): self
    {
        return $this->add("$column NOT LIKE :$column", [$column => $pattern]);
    }

    public function orNotLike(string $column, string $pattern): self
    {
        return $this->add("$column NOT LIKE :$column", [$column => $pattern], "OR");
    }

    public function andBetween(string $column, mixed $min, mixed $max): self
    {
        return $this->add("$column BETWEEN :{$column}_min AND :{$column}_max", [
            "{$column}_min" => $min,
            "{$column}_max" => $max,
        ]);
    }

    public function orBetween(string $column, mixed $min, mixed $max): self
    {
        return $this->add("$column BETWEEN :{$column}_min AND :{$column}_max", [
            "{$column}_min" => $min,
            "{$column}_max" => $max,
        ], "OR");
    }

    public function andIn(string $column, array $values): self
    {
        return $this->buildInClause($column, $values, "IN", "AND");
    }

    public function orIn(string $column, array $values): self
    {
        return $this->buildInClause($column, $values, "IN", "OR");
    }

    public function andNotIn(string $column, array $values): self
    {
        return $this->buildInClause($column, $values, "NOT IN", "AND");
    }

    public function orNotIn(string $column, array $values): self
    {
        return $this->buildInClause($column, $values, "NOT IN", "OR");
    }

    public function isNull(string $column, string $glue = "AND"): self
    {
        return $this->add("$column IS NULL", [], $glue);
    }

    public function isNotNull(string $column, string $glue = "AND"): self
    {
        return $this->add("$column IS NOT NULL", [], $glue);
    }

    public function where(string $operator, string $column, mixed $value, string $glue = "AND"): self
    {
        return $this->add("$column $operator :$column", [$column => $value], $glue);
    }

    private function add(string $sql, array $params, string $glue = "AND"): self
    {
        $this->expressions[] = ["sql" => $sql, "params" => $params, "glue" => $glue];
        return $this;
    }

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
