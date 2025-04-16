<?php

namespace ORM\Relation;

use Closure;
use ORM\Entity\EntityManager;
use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;

readonly class LazyOneToOneHydrator implements RelationHydrator
{
    public function __construct(private EntityManager $entityManager)
    {}

    public function supports(array $relation): bool
    {
        return $relation["relation"]->fetch === FetchType::Lazy
            && isset($relation["joinColumn"]);
    }

    public function hydrate(
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $data
    ): ?Closure
    {
        $joinColumn = $relation["joinColumn"];
        $fkKey = "{$parentMetadata->getAlias()}_{$joinColumn->name}";
        $fkValue = $data[$fkKey] ?? null;

        if ($fkValue === null) {
            return null;
        }

        return fn() => $this->entityManager->findBy(
            $relation["relation"]->entity,
            $fkValue
        );
    }
}
