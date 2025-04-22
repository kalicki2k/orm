<?php

namespace ORM\Hydration;

use DateMalformedStringException;
use ORM\Attributes\ManyToOne;
use ORM\Entity\EntityBase;
use ORM\Entity\EntityManager;
use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;
use ReflectionException;

final readonly class EagerManyToOneHydrator implements RelationHydrator
{
    public function __construct(private EntityManager $entityManager) {}

    public function supports(array $relation): bool
    {
        return $relation['relation'] instanceof ManyToOne
            && $relation['relation']->fetch === FetchType::Eager
            && isset($relation['joinColumn']);
    }

    /**
     * @param EntityBase $parentEntity
     * @param MetadataEntity $parentMetadata
     * @param string $property
     * @param array $relation
     * @param array $row
     * @return EntityBase|null
     * @throws DateMalformedStringException
     * @throws ReflectionException
     */
    public function hydrate(
        EntityBase $parentEntity,
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $row
    ): ?EntityBase {
        $relationAlias = $parentMetadata->getRelationAlias($property);

        $relationData = array_filter(
            $row,
            fn($key) => str_starts_with($key, "{$relationAlias}_"),
            ARRAY_FILTER_USE_KEY
        );

        $hasData = count(array_filter($relationData, fn($v) => $v !== null)) > 0;
        if (!$hasData) {
            return null;
        }

        $relatedMetadata = $this->entityManager->getMetadata($relation["relation"]->entity);
        $relatedMetadata->setAlias($relationAlias);

        return $this->entityManager->hydrateEntity($relatedMetadata, $row);
    }
}
