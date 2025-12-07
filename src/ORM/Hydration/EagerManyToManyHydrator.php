<?php

namespace ORM\Hydration;

use ORM\Attributes\ManyToMany;
use ORM\Cache\ReflectionCache;
use ORM\Collection;
use ORM\Entity\EntityBase;
use ORM\Entity\EntityManager;
use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;
use ORM\Metadata\MetadataParser;

class EagerManyToManyHydrator implements RelationHydrator
{
    public function __construct(
        private EntityManager $entityManager,
        private ReflectionCache $reflectionCache,
        private MetadataParser $metadataParser
    ) {}
    public function supports(array $relation): bool
    {
        return $relation["relation"] instanceof ManyToMany
            && $relation["relation"]->fetch === FetchType::Eager
            && isset($relation["joinTable"]);
    }

    public function hydrate(
        EntityBase $parentEntity,
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $row
    ): Collection
    {
        $relationAlias = $parentMetadata->getRelationAlias($property);
        $relationData = array_filter(
            $row,
            fn($key) => str_starts_with($key, $relationAlias . "_"),
            ARRAY_FILTER_USE_KEY
        );

        if (!count(array_filter($relationData, fn($v) => $v !== null)) > 0) {
            return new Collection();
        }

        $entity = $relation["relation"]->entity;
        $metadata = $this->metadataParser->parse($entity);
        $metadata->setAlias($relationAlias);

        $child = $this->entityManager->hydrateEntity($metadata, $row);

        if (
            $this->reflectionCache->hasProperty($parentEntity, $property)
            && $this->reflectionCache->isInitialized($parentEntity, $property)
        ) {
            $collection = $this->reflectionCache->getValue($parentEntity, $property);
        } else {
            $collection = new Collection();
        }

        $childId = $this->metadataParser->extract($child)[$metadata->getPrimaryKey()];
        $alreadyExists = false;

        foreach ($collection as $existing) {
            $existingId = $this->metadataParser->extract($existing)[$metadata->getPrimaryKey()];
            if ($existingId === $childId) {
                $alreadyExists = true;
                break;
            }
        }

        if (!$alreadyExists) {
            $collection->add($child);
        }

        return $collection;
    }
}