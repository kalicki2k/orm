<?php

namespace ORM\Hydration;

use Closure;
use ORM\Attributes\ManyToMany;
use ORM\Collection;
use ORM\Entity\EntityBase;
use ORM\Entity\EntityManager;
use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;
use ORM\Query\Expression;

final readonly class LazyManyToManyHydrator implements RelationHydrator
{
    public function __construct(private EntityManager $entityManager) {}

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
        $joinTable = $relation["joinTable"];
        $entity = $relation["relation"]->entity;

        $parentId = $parentEntity->getId();

        return fn() => $this->entityManager->findAll(
            $entity,
            Expression::eq($joinTable->joinColumn, $parentId),
            [
                "joins" => [
                    [
                        "type" => "inner",
                        "table" => $joinTable->name,
                        "on" => [
                            "{$joinTable->inverseJoinColumn}" => "id"
                        ]
                    ]
                ],
            ]
        );
    }
}
