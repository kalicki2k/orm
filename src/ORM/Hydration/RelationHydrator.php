<?php

namespace ORM\Hydration;

use Closure;
use ORM\Collection;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataEntity;

interface RelationHydrator
{
    public function supports(array $relation): bool;

    public function hydrate(
        EntityBase $parentEntity,
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $row,
    ): Closure|Collection|EntityBase|null;
}
