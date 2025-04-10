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
        // Efficiently retrieve cached ReflectionClass instance for the entity
        $reflectionClass = ReflectionCache::get($entity);

        // Retrieve the #[Table] attribute from the class (required for ORM mapping)
        $attributes = $reflectionClass->getAttributes(Table::class);

        if (empty($attributes)) {
            throw new RuntimeException("Missing #[Table] attribute on class " . $reflectionClass->getName());
        }

        /** @var Table $table */
        $table = $attributes[0]->newInstance();
        $columns = [];
        $relations = [];

        // Loop through all properties and collect column definitions
        foreach ($reflectionClass->getProperties() as $property) {
            var_dump($property);

            self::parseColumn($property, $columns);
            self::parseRelation($property, $relations);
        }

        var_dump($relations);

        return [
            $table->name, // Name of the table
            $columns, // Array of columns metadata indexed by property name
            $relations, // Array containing relation types and their target data
        ];
    }

    /**
     * Parses a property annotated with #[Column] and adds its metadata to the result array.
     *
     * @param ReflectionProperty $property
     * @param array $columns Reference to the result array to populate
     * @return void
     */
    private static function parseColumn(ReflectionProperty $property, array &$columns): void
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
            "column"        => $column->name,
            "type"          => $column->type,
            "length"        => $column->length,
            "primary"       => $column->primary,
            "autoIncrement" => $column->autoIncrement,
            "nullable"      => $column->nullable,
            "default"       => $column->default,
        ];

        // Add generation strategy if PrimaryGeneratedColumn
        if ($column instanceof PrimaryGeneratedColumn) {
            $columns[$property->getName()]["strategy"] = $column->type === "uuid" ? "uuid" : "auto";
        }
    }

    /**
     * @param ReflectionProperty $property
     * @param array $relations
     * @return void
     */
    private static function parseRelation(ReflectionProperty $property, array &$relations): void
    {
        $oneToOneAttribute = $property->getAttributes(OneToOne::class);
        $joinColumnAttribute = $property->getAttributes(JoinColumn::class);

        if (!empty($oneToOneAttribute) && !empty($joinColumnAttribute)) {
            $relation = $oneToOneAttribute[0]->newInstance();
            $join = $joinColumnAttribute[0]->newInstance();

            $relations[$property->getName()] = [
                "type" => "OneToOne",
                "entity" => $relation->entity,
                "foreignKey" => "$join->name",
                "referencedColumn" => $join->referencedColumn,
            ];
        }
    }
}