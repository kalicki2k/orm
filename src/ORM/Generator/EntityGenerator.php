<?php

namespace ORM\Generator;

use ORM\Drivers\DatabaseDriver;

class EntityGenerator
{
    public function __construct(private DatabaseDriver $databaseDriver) {}

    public function generate(string $table, string $fqcn): string
    {
        $columns = $this->fetchColumns($table);
        $className = basename(str_replace("\\", "/", $fqcn));
        $namespace = str_contains($fqcn, "\\") ? substr($fqcn, 0, strrpos($fqcn, "\\")) : "";

        $code = "<?php\n\n";
        $code .= "namespace $namespace;\n\n";

        $code .= "use ORM\\Attributes\\Column;\n";
        $code .= "use ORM\\Attributes\\Entity;\n";
        $code .= "use ORM\\Attributes\\Id;\n";
        $code .= "use ORM\\Attributes\\GeneratedValue;\n";
        $code .= "use ORM\\Attributes\\Table;\n";
        $code .= "use ORM\\Entity\\EntityBase;\n\n";

        $code .= "use ORM\\Attributes\\JoinColumn;\n";
        $code .= "use ORM\\Attributes\\OneToOne;\n";
        $code .= "use ORM\\Attributes\\ManyToOne;\n";

        $code .= "#[Entity]\n";
        $code .= "#[Table(\"$table\")]\n";
        $code .= "class $className extends EntityBase\n{\n";

        $foreignKeys = $this->fetchForeignKeys($table);
        $foreignKeyMap = [];

        foreach ($foreignKeys as $foreignKey) {
            $foreignKeyMap[$foreignKey["COLUMN_NAME"]] = [
                "referencedTable" => $foreignKey["REFERENCED_TABLE_NAME"],
                "referencedColumn" => $foreignKey["REFERENCED_COLUMN_NAME"],
                "isUnique" => $this->isUnique($table, $foreignKey["COLUMN_NAME"]),
            ];
        }

        foreach ($columns as $column) {
            $name = $column["COLUMN_NAME"];
            $type = $this->mapType($column["DATA_TYPE"]);
            $nullable = $column["IS_NULLABLE"] === "YES" ? "true" : "false";

            if (isset($foreignKeyMap[$name])) {
                $targetEntity = $this->resolveEntityClass($foreignKeyMap[$name]["referencedTable"]);

                if ($foreignKeyMap[$name]["isUnique"]) {
                    $code .= "    #[OneToOne(entity: $targetEntity::class)]\n";
                } else {
                    $code .= "    #[ManyToOne(entity: $targetEntity::class)]\n";
                }

                $code .= "    #[JoinColumn(name: \"$name\", referencedColumnName: \"{$foreignKeyMap[$name]["referencedColumn"]}\")]\n";
                $code .= "    private $targetEntity \$$name;\n\n";

                continue;
            }


            $code .= "    #[Column(type: \"$type\", name: \"$name\", nullable: $nullable)]\n";
            if ($column["COLUMN_KEY"] === "PRI") {
                $code .= "    #[Id]\n";
                if ($column['EXTRA'] === 'auto_increment') {
                    $code .= "    #[GeneratedValue]\n";
                }
            }

            $phpType = $type === "int" ? "int" : ($type === "string" ? "string" : "mixed");
            $code .= "    private $phpType \$$name;\n\n";
        }

        $code .= $this->generateGetters($columns, $foreignKeyMap ?? []);
        $code .= "}\n";
        return $code;
    }

    private function fetchColumns(string $table): array
    {
        $statement = $this->databaseDriver->prepare("
            SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
        ");
        $statement->bindValue(":table", $table);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function fetchForeignKeys(string $table): array
    {
        $statement = $this->databaseDriver->prepare("
            SELECT 
                k.COLUMN_NAME,
                k.REFERENCED_TABLE_NAME,
                k.REFERENCED_COLUMN_NAME,
                tc.CONSTRAINT_TYPE
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k
            LEFT JOIN INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc 
                ON k.CONSTRAINT_NAME = tc.CONSTRAINT_NAME
            WHERE 
                k.TABLE_NAME = :table
                AND k.TABLE_SCHEMA = DATABASE()
                AND k.REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $statement->bindValue(":table", $table);
        $statement->execute();

        return $statement->fetchAll();
    }

    private function isUnique(string $table, string $column): bool
    {
        $statement = $this->databaseDriver->prepare("
            SELECT COUNT(*) as `count`
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
            AND COLUMN_NAME = :column
            AND NON_UNIQUE = 0
        ");

        $statement->bindValue(":table", $table);
        $statement->bindValue(":column", $column);
        $statement->execute();

        $row = $statement->fetch();
        return (int) $row["count"] > 0;
    }

    private function mapType(string $sqlType): string
    {
        return match (strtolower($sqlType)) {
            "int", "bigint", "smallint", "tinyint" => "int",
            "varchar", "text", "char", "longtext" => "string",
            "datetime", "timestamp", "date" => "datetime",
            "json" => "json",
            default => "mixed",
        };
    }

    private function resolveEntityClass(string $tableName): string
    {
        $class = "Entity\\" . str_replace(" ", "", ucwords(str_replace("_", " ", $tableName)));
        $singular = match (true) {
            str_ends_with($class, 'ies') => substr($class, 0, -3) . 'y',   // categories → Category
            str_ends_with($class, 'ses') => substr($class, 0, -2),         // statuses → Status
            str_ends_with($class, 's') => substr($class, 0, -1),           // users → user
            default => $class,
        };

        return "Entity\\$singular";
    }

    private function generateGetters(array $columns, array $foreignKeyMap): string
    {
        $output = "";

        foreach ($columns as $col) {
            $name = $col['COLUMN_NAME'];

            if (isset($foreignKeyMap[$name])) {
                $type = $this->resolveEntityClass($foreignKeyMap[$name]['referencedTable']);
            } else {
                $mapped = $this->mapType($col['DATA_TYPE']);
                $type = match ($mapped) {
                    'int' => 'int',
                    'string' => 'string',
                    'datetime' => '\DateTimeInterface',
                    default => 'mixed',
                };
            }

            $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));

            $output .= <<<PHP

    public function $method(): $type
    {
        return \$this->$name;
    }

PHP;
        }

        return $output;
    }

}
