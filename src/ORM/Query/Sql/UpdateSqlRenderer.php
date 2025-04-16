<?php

namespace ORM\Query\Sql;

use ORM\Query\QueryBuilder;
use RuntimeException;

final class UpdateSqlRenderer implements SqlRenderer
{
    public function render(QueryBuilder $queryBuilder): string
    {
        $values = $queryBuilder->getValues();

        if (empty($values)) {
            throw new RuntimeException("No values set for update.");
        }

        $setParts = [];
        foreach ($values as $column => $_) {
            $quoted = $queryBuilder->getDatabaseDriver()->quoteIdentifier($column);
            $setParts[] = "{$quoted} = :{$column}";
        }

        $sql = "UPDATE {$queryBuilder->getTable()} SET " . implode(', ', $setParts);

        if (!empty($queryBuilder->getWhere())) {
            $whereParts = [];
            foreach ($queryBuilder->getWhere() as $key => $value) {
                $whereParts[] = "$key = {$value}";
            }
            $sql .= " WHERE " . implode(" AND ", $whereParts);
        }

        return $sql;
    }
}
