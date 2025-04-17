<?php

namespace ORM\Query;

/**
 * Encapsulates the current query state used by QueryBuilder and Renderers.
 *
 * This context object carries all necessary components to build and render SQL:
 * - action type (select, insert, update, delete)
 * - target table
 * - selected columns
 * - WHERE conditions
 * - JOIN clauses
 * - named SQL parameters
 *
 * @see \ORM\Query\QueryBuilder
 */
final class QueryContext
{
    /**
     * The SQL action type (select, insert, update, delete).
     *
     * @var string|null
     */
    public ?string $action = null;

    /**
     * The quoted table name (with alias if SELECT).
     *
     * @var string|null
     */
    public ?string $table = null;

    public ?string $alias = null;

    /**
     * List of columns or expressions (already quoted and aliased).
     *
     * @example ["u.id AS u_id", "u.name AS u_name"]
     * @var string[]
     */
    public array $columns = [];

    /**
     * Key-value pairs for INSERT or UPDATE payloads.
     *
     * @example ["username" => "alice", "email" => "a@b.c"]
     * @var array<string, mixed>
     */
    public array $values = [];

    /**
     * WHERE conditions with quoted keys.
     *
     * @example ["`u`.`id`" => ":id"]
     * @var array<string, string>
     */
    public array $where = [];

    /**
     * JOIN definitions with quoted ON conditions.
     *
     * @example [['type' => 'LEFT', 'table' => '`profiles`', 'alias' => '`p`', 'on' => '`u`.`id` = `p`.`user_id`']]
     * @var array<int, array{type: string, table: string, alias: string, on: string}>
     */
    public array $joins = [];

    /**
     * SQL parameters to bind.
     *
     * @example ["id" => 123, "email" => "test@example.com"]
     * @var array<string, mixed>
     */
    public array $parameters = [];

    public function useAlias(): bool
    {
        return $this->action === 'select' && !empty($this->alias);
    }
}