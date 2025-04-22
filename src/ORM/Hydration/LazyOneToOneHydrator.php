<?php

namespace ORM\Hydration;

use Closure;
use ORM\Attributes\OneToOne;
use ORM\Entity\EntityBase;
use ORM\Entity\EntityManager;
use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;

final readonly class LazyOneToOneHydrator implements RelationHydrator
{
    public function __construct(private EntityManager $entityManager) {}

    public function supports(array $relation): bool
    {
        return $relation["relation"] instanceof OneToOne
            && $relation["relation"]->fetch === FetchType::Lazy;
    }

    public function hydrate(
        EntityBase $parentEntity,
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $row
    ): ?Closure
    {
        /** @var OneToOne $oneToOne */
        $oneToOne = $relation["relation"];
        $entity = $oneToOne->entity;
        $mappedBy = $oneToOne->mappedBy ?? null;
        $joinColumn = $relation["joinColumn"] ?? null;

        // Owning side
        if ($joinColumn !== null) {
            $foreignKey = $row["{$parentMetadata->getAlias()}_{$joinColumn->name}"] ?? null;

            if ($foreignKey === null) {
                return null;
            }

            return fn() => $this->entityManager->findBy($entity, $foreignKey);
        }

        // Inverse side
        if ($mappedBy !== null) {
            $localId = $row["{$parentMetadata->getAlias()}_{$parentMetadata->getPrimaryKey()}"] ?? null;

            if ($localId === null) {
                return null;
            }

            $targetMetadata = $this->entityManager->getMetadata($entity);
            $targetRelation = $targetMetadata->getRelations()[$mappedBy] ?? null;
            $targetJoinColumn = $targetRelation['joinColumn'] ?? null;

            if (!$targetJoinColumn) {
                return null;
            }

            return fn() => $this->entityManager->findBy($entity, [
                $targetJoinColumn->name => $localId,
            ]);
        }

        return null;
    }
}
