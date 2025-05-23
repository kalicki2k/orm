<?php

namespace ORM\Metadata;

use LogicException;
use ORM\Attributes\Column;
use ORM\Attributes\JoinColumn;
use ORM\Attributes\JoinTable;
use ORM\Attributes\ManyToMany;

/**
 * Represents the ORM metadata for a single entity class.
 *
 * This class holds information about the entity's table, alias, columns, primary key,
 * and its relations to other entities. The data is used throughout the ORM
 * for query generation, hydration, and mapping.
 */
class MetadataEntity
{
    /**
     * The table alias used for this entity (e.g., 'user').
     */
    protected string $alias;

    /**
     * The name of the database table this entity maps to.
     */
    protected string $table;

    /**
     * The name of the primary key column, if set.
     */
    protected ?string $primaryKey = null;

    /**
     * Indicates whether the primary key is auto-generated.
     */
    protected bool $primaryKeyGenerated = false;

    /**
     * List of entity columns, keyed by property name.
     *
     * @var array<string, array{name: string, attributes: Column}>
     */
    protected array $columns = [];

    /**
     * List of entity relations, keyed by property name.
     *
     * @var array<string, array{relation: object, joinColumn?: ?JoinColumn}>
     */
    protected array $relations = [];

    /**
     * @param string $entityName The fully qualified class name of the entity.
     */
    public function __construct(protected string $entityName) {}

    /**
     * Sets the alias used in SQL queries for this entity.
     *
     * @param string $alias The alias (e.g., 'user').
     * @return $this
     */
    public function setAlias(string $alias): MetadataEntity
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * Returns the alias used in SQL queries for this entity.
     *
     * @return string
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * Returns the aliased column name for a given property.
     *
     * @param string $propertyName The name of the entity property.
     * @return string The SQL alias (e.g., 'users_email').
     */
    public function getColumnAlias(string $propertyName): string
    {
        return "{$this->alias}_{$this->columns[$propertyName]['name']}";
    }

    /**
     * Returns the alias used for a relation join.
     *
     * @param string $propertyName The name of the relation property.
     * @return string The SQL alias (e.g., 'users__profile').
     */
    public function getRelationAlias(string $propertyName): string
    {
        return "{$this->alias}__{$propertyName}";
    }

    /**
     * Sets the table name for this entity.
     *
     * @param string $table The name of the database table.
     * @return $this
     */
    public function setTable(string $table): MetadataEntity
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Returns the database table name.
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Sets the primary key column.
     *
     * @param string $columnName The name of the column.
     * @throws LogicException If the primary key is already set.
     */
    public function setPrimaryKey(string $columnName): void
    {
        if ($this->primaryKey !== null) {
            throw new LogicException("Primary key is already set to {$this->primaryKey}.");
        }
        $this->primaryKey = $columnName;
    }

    /**
     * Returns the primary key column name.
     *
     * @return string|null
     */
    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    /**
     * Marks whether the primary key is auto-generated.
     *
     * @param bool $isGenerated
     */
    public function setPrimaryKeyGenerated(bool $isGenerated): void
    {
        $this->primaryKeyGenerated = $isGenerated;
    }

    /**
     * Returns whether the primary key is auto-generated.
     *
     * @return bool
     */
    public function isPrimaryKeyGenerated(): bool
    {
        return $this->primaryKeyGenerated;
    }

    /**
     * Adds a column mapping to the entity.
     *
     * @param Column $column The column attribute instance.
     * @return $this
     */
    public function addColumn(Column $column): MetadataEntity
    {
        $this->columns[$column->name] = [
            "name" => $column->name,
            "attributes" => $column,
        ];
        return $this;
    }

    /**
     * Returns all column mappings.
     *
     * @return array<string, array{name: string, attributes: Column}>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Adds a relation mapping to the entity.
     *
     * This method is used to define a relationship mapping for an entity property.
     * The relation can be of type OneToOne, ManyToOne, ManyToMany, etc. Depending
     * on the type of relation, the method decides whether to use a join column or
     * a join table for defining the mapping.
     *
     * @param string $propertyName The name of the property representing the relation.
     * @param object $relation The relation attribute instance (e.g., ManyToMany, OneToOne).
     *                         Must be an instance of a relation attribute class.
     * @param JoinTable|JoinColumn|null $joinMeta The optional metadata for the join.
     *                                            For ManyToMany, this should be a JoinTable.
     *                                            For OneToOne or ManyToOne, this should be a JoinColumn.
     */
    public function addRelation(string $propertyName, object $relation, JoinColumn|JoinTable|null $joinMeta = null): void
    {
        $relationType = $relation instanceof ManyToMany ? "joinTable" : "joinColumn";

        $this->relations[$propertyName] = [
            "relation" => $relation,
            $relationType => $joinMeta,
        ];
    }

    /**
     * Returns all relation mappings.
     *
     * @return array<string, array{relation: object, joinColumn?: ?JoinColumn}>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Returns the fully qualified class name of the entity.
     *
     * @return string
     */
    public function getEntityName(): string
    {
        return $this->entityName;
    }
}
