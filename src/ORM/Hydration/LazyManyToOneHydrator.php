<?php

namespace ORM\Hydration;

use Closure;
use ORM\Attributes\ManyToOne;
use ORM\Entity\EntityBase;
use ORM\Entity\EntityManager;
use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;

final readonly class LazyManyToOneHydrator implements RelationHydrator
{
    public function __construct(private EntityManager $entityManager) {}

    public function supports(array $relation): bool
    {
        return $relation['relation'] instanceof ManyToOne
            && $relation['relation']->fetch === FetchType::Lazy
            && isset($relation['joinColumn']);
    }

    public function hydrate(
        EntityBase $parentEntity,
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $row
    ): ?Closure {
        $joinColumn = $relation['joinColumn'];
        $foreignKey = $row["{$parentMetadata->getAlias()}_{$joinColumn->name}"] ?? null;

        if ($foreignKey === null) {
            return null;
        }

        return fn() => $this->entityManager->findBy(
            $relation['relation']->entity,
            $foreignKey
        );
    }
}
