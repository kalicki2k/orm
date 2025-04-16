<?php

namespace ORM\Query\Sql;

use ORM\Query\QueryBuilder;

final class DeleteSqlRenderer implements SqlRenderer
{
    public function render(QueryBuilder $queryBuilder): string
    {
        $sql = "DELETE FROM {$queryBuilder->getTable()}";

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
