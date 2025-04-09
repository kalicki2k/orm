<?php

namespace ORM;

use ORM\Attributes\Column;
use ORM\Attributes\JoinColumn;
use ORM\Attributes\OneToOne;
use ORM\Attributes\PrimaryGeneratedColumn;
use ORM\Attributes\Table;
use ORM\Util\ReflectionCache;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

/**
 * Class MetadataParser
 *
 * Parses PHP attributes on entity classes to extract ORM metadata.
 */
class MetadataParser
{
    /**
     * Parses metadata (table name and columns) from a given entity instance.
     *
     * @param object $entity An instance of the entity to inspect
     * @return array An array with [tableName, columnMetadata]
     *
     * @throws RuntimeException If no #[Table] attribute is found
     * @throws ReflectionException If the class cannot be reflected
     */
    public static function parse(object $entity): array
    {
        $reflectionClass = ReflectionCache::get($entity);
        $attributes = $reflectionClass->getAttributes(Table::class);

        if (empty($attributes)) {
            throw new RuntimeException("Missing #[Table] attribute on class " . $reflectionClass->getName());
        }

        /** @var Table $table */
        $table = $attributes[0]->newInstance();
        $columns = [];
        $relations = [];

        foreach ($reflectionClass->getProperties() as $property) {
            self::parseColumn($table->name, $property, $columns);
            self::parseRelation($property, $relations);
            self::parseJoinColumn($table->name, $property, $columns);
        }

        return [
            $table->name,
            $columns,
            $relations,
        ];
    }

    /**
     * Parses a property annotated with #[Column] and adds its metadata to the result array.
     *
     * @param string $table
     * @param ReflectionProperty $property
     * @param array $columns Reference to the result array to populate
     *
     * @return void
     */
    private static function parseColumn(string $table, ReflectionProperty $property, array &$columns): void
    {
        // Get all #[Column] and #[PrimaryGeneratedColumn] attributes on the property
        $attributes = array_merge(
            $property->getAttributes(Column::class),
            $property->getAttributes(PrimaryGeneratedColumn::class)
        );

        if (empty($attributes)) {
            return;
        }

        /** @var Column $column */
        $column = $attributes[0]->newInstance();
        $columns[$property->getName()] = [
            "table" => $table,
            "name" => $column->name,
            "alias" => "{$table}__{$column->name}",
            "type" => $column->type,
            "length" => $column->length,
            "primary" => $column->primary,
            "autoIncrement" => $column->autoIncrement,
            "nullable" => $column->nullable,
            "default" => $column->default,
        ];

        // Add generation strategy if PrimaryGeneratedColumn
        if ($column instanceof PrimaryGeneratedColumn) {
            $columns[$property->getName()]["strategy"] = $column->type === "uuid" ? "uuid" : "auto";
        }
    }

    /**
     * @param ReflectionProperty $property
     * @param array $relations
     *
     * @return void
     *
     * @throws ReflectionException
     */
    private static function parseRelation(ReflectionProperty $property, array &$relations): void
    {
        $oneToOneAttributes = $property->getAttributes(OneToOne::class);
        if (empty($oneToOneAttributes)) {
            return;
        }

        /** @var OneToOne $oneToOne */
        $oneToOne = $oneToOneAttributes[0]->newInstance();

        $targetReflection = ReflectionCache::get($oneToOne->entity);
        $tableAttrs = $targetReflection->getAttributes(Table::class);
        if (empty($tableAttrs)) {
            return;
        }

        /** @var Table $targetTableInstance */
        $targetTableInstance = $tableAttrs[0]->newInstance();
        $targetTableName = $targetTableInstance->name;

        if (isset($oneToOne->mappedBy)) {
            // Inverse side
            $owningPropertyName = $oneToOne->mappedBy;
            if (!$targetReflection->hasProperty($owningPropertyName)) {
                return;
            }
            $owningProperty = $targetReflection->getProperty($owningPropertyName);
            $joinColumnAttributes = $owningProperty->getAttributes(JoinColumn::class);
            if (empty($joinColumnAttributes)) {
                return;
            }
            $join = $joinColumnAttributes[0]->newInstance();
            $inverse = true;
        } else {
            // Owning side
            $joinColumnAttributes = $property->getAttributes(JoinColumn::class);
            if (empty($joinColumnAttributes)) {
                return;
            }
            $join = $joinColumnAttributes[0]->newInstance();
            $inverse = false;
        }

        $relations[$property->getName()] = [
            "type" => "OneToOne",
            "entity" => $oneToOne->entity,
            "table" => $targetTableName,
            "foreignKey" => $join->name,
            "referencedColumn" => $join->referencedColumn,
            "alias" => strtolower($targetTableName . '__' . $property->getName()),
            "inverse" => $inverse,
        ];
    }

    private static function parseJoinColumn(string $table, ReflectionProperty $property, array &$columns): void
    {
        // Falls bereits ein Eintrag für diese Property vorhanden ist, mache nichts.
        if (isset($columns[$property->getName()])) {
            return;
        }

        $joinAttrs = $property->getAttributes(\ORM\Attributes\JoinColumn::class);
        $oneToOneAttrs = $property->getAttributes(\ORM\Attributes\OneToOne::class);

        if (!empty($joinAttrs) && !empty($oneToOneAttrs)) {
            /** @var \ORM\Attributes\JoinColumn $join */
            $join = $joinAttrs[0]->newInstance();

            // Ermittele die Zielspalte (über die referenzierte Spalte in der Zielentität)
            $targetEntity = $oneToOneAttrs[0]->newInstance()->entity;
            $targetMetadata = MetadataParser::parse(new $targetEntity());
            $targetColumns = $targetMetadata[1];

            $targetColumnData = null;
            foreach ($targetColumns as $colData) {
                if ($colData["name"] === $join->referencedColumn) {
                    $targetColumnData = $colData;
                    break;
                }
            }
            if ($targetColumnData !== null) {
                $type          = $targetColumnData["type"];
                $primary       = $targetColumnData["primary"];
                $autoIncrement = $targetColumnData["autoIncrement"];
                $nullable      = $targetColumnData["nullable"];
                $default       = $targetColumnData["default"];
            } else {
                $type          = "int";
                $primary       = false;
                $autoIncrement = false;
                $nullable      = false;
                $default       = null;
            }

            $columns[$property->getName()] = [
                "table"         => $table,
                "name"          => $join->name,
                "alias"         => "{$table}__{$join->name}",
                "type"          => $type,
                "primary"       => $primary,
                "autoIncrement" => $autoIncrement,
                "nullable"      => $nullable,
                "default"       => $default,
                "joinColumn"    => true,  // Markiere diesen Eintrag als Join-Column
            ];
        }
    }



}