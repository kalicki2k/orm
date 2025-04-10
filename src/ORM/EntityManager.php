<?php

namespace ORM;

use http\Exception\InvalidArgumentException;
use ORM\Drivers\DatabaseDriver;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;
use ORM\Query\QueryBuilder;
use Psr\Log\LoggerInterface;

class EntityManager {
    protected QueryBuilder $queryBuilder;
    /**
     * EntityManager constructor.
     *
     * @param DatabaseDriver $databaseDriver The database driver implementation (e.g., PDO-based).
     * @param LoggerInterface|null $logger Optional PSR-3 logger for SQL and debug output.
     */
    public function __construct(
        private readonly DatabaseDriver $databaseDriver,
        private readonly MetadataParser $metadataParser,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->queryBuilder = new QueryBuilder($this->databaseDriver);
    }

    public function find(string $entityName, mixed $id): ?object
    {

        $metadata = $this->getMetadata(ltrim($entityName, "\\"));
        $select = [];
        $where = [];
        $parameters = [];

        foreach ($metadata->getColumns() as $column) {
            $select[] = $column["name"];
        }

        foreach ($metadata->getRelations() as $relation) {
            if (array_key_exists("joinColumn", $relation)) {
                $select[] = $relation["joinColumn"]->name;
            }
        }

        // @todo Fix error message
        // @todo And how to handle array with where conditions?
        if (!is_array($id)) {
            $primaryKey = $metadata->getPrimaryKey();

            if (!isset($primaryKey)) {
                throw new InvalidArgumentException("Primary key does not exist");
            }

            $where[$primaryKey] = ":{$primaryKey}";
            $parameters[$primaryKey] = $id;
        } else {
            foreach ($id as $key => $value) {
                $where[$key] = ":{$key}";
                $parameters[$key] = $value;
            }
        }

        $statement = new QueryBuilder($this->databaseDriver)->table($metadata->getTable())
            ->select($select)
            ->where($where, $parameters)
            ->execute();
        $data = $statement->fetch();

        var_dump($data);

        return $statement;
    }

    protected function getMetadata(string $entityName): MetadataEntity
    {
        return $this->metadataParser->parse($entityName);
    }
}