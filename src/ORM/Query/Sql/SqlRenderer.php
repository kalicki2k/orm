<?php

namespace ORM\Query\Sql;

use ORM\Query\QueryBuilder;

interface SqlRenderer
{
    public function render(QueryBuilder $queryBuilder): string;
}