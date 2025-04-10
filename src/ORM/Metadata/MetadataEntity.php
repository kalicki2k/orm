<?php

namespace ORM\Metadata;

use LogicException;
use ORM\Attributes\Column;
use ORM\Attributes\JoinColumn;

class MetadataEntity
{
    protected string $table;
    protected ?string $primaryKey = null;
    protected bool $primaryKeyGenerated = false;
    protected array $columns = [];

    /**
     * @var array<string, array{relation: object, joinColumn?: ?object}>
     */
    protected array $relations = [];

    public function __construct(protected string $entityName) {}

    public function setTable(string $table): MetadataEntity
    {
        $this->table = $table;
        return $this;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Setzt den Primärschlüssel der Entität.
     *
     * @param string $columnName Der Name der Spalte, die als Primärschlüssel dient.
     *
     * @throws LogicException Falls bereits ein Primärschlüssel gesetzt wurde.
     */
    public function setPrimaryKey(string $columnName): void
    {
        if ($this->primaryKey !== null) {
            throw new LogicException("Primary key is already set to {$this->primaryKey}.");
        }
        $this->primaryKey = $columnName;
    }

    public function getPrimaryKey(): ?string
    {
        return $this->primaryKey;
    }

    public function setPrimaryKeyGenerated(bool $isGenerated): void
    {
        $this->primaryKeyGenerated = $isGenerated;
    }

    public function isPrimaryKeyGenerated(): bool
    {
        return $this->primaryKeyGenerated;
    }

    public function addColumn(Column $column): MetadataEntity
    {
        $this->columns[$column->name] = [
            'name' => $column->name,
            'attributes' => $column,
        ];
        return $this;
    }

    public function getColumns(): array
    {
        return $this->columns;
    }


    public function addRelation(string $propertyName, object $relation, ?JoinColumn $joinColumn = null): void
    {
        $this->relations[$propertyName] = [
            'relation' => $relation,
            'joinColumn' => $joinColumn,
        ];
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }
}