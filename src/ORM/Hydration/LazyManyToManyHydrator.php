<?php

namespace ORM\Hydration;

use Closure;
use DateMalformedStringException;
use ORM\Attributes\ManyToMany;
use ORM\Collection;
use ORM\Entity\EntityBase;
use ORM\Entity\EntityManager;
use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;
use ORM\Query\Expression;
use ORM\Query\QueryBuilder;
use ReflectionException;

final readonly class LazyManyToManyHydrator implements RelationHydrator
{
    public function __construct(private EntityManager $entityManager, private MetadataParser $metadataParser) {}

    public function supports(array $relation): bool
    {
        return $relation["relation"] instanceof ManyToMany
            && $relation["relation"]->fetch === FetchType::Lazy
            && isset($relation["joinTable"]);
    }

    public function hydrate(
        EntityBase $parentEntity,
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $row
    ): Closure {
        return function() use ($parentEntity, $relation) {
            return $this->loadCollection($parentEntity, $relation);
        };
    }

    private function applySelectColumns(QueryBuilder $queryBuilder, MetadataEntity $metadata, string $alias): void
    {
        foreach ($metadata->getColumns() as $column) {
            $queryBuilder->select([
                "$alias.{$column["name"]}" => "{$alias}_{$column["name"]}"
            ]);
        }
    }

    /**
     * @throws ReflectionException
     * @throws DateMalformedStringException
     */
    private function loadCollection(EntityBase $entity, array $relation): Collection
    {
        $joinTable = $relation["joinTable"];
        $targetEntity = $relation["relation"]->entity;

        $ownerMetadata = $this->entityManager->getMetadata($entity::class);
        $targetMetadata = $this->entityManager->getMetadata($targetEntity);

        $ownerId = $this->metadataParser->extract($entity)[$ownerMetadata->getPrimaryKey()];
        $alias = $targetMetadata->getAlias();

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->table($targetMetadata->getTable(), $alias);

        $this->applySelectColumns($queryBuilder, $targetMetadata, $alias);

        $rows = $queryBuilder
            ->innerJoin(
                $joinTable->name,
                "jt",
                "jt.$joinTable->inverseJoinColumn = $alias.{$targetMetadata->getPrimaryKey()}"
            )
            ->where(Expression::eq("jt.$joinTable->joinColumn", $ownerId))
            ->execute()
            ->fetchAll();

        $entities = array_map(fn($row) => $this->entityManager->hydrateEntity($targetMetadata, $row), $rows);

        return new Collection($entities);
    }
}
