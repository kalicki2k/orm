<?php

namespace ORM\Hydration;

use Closure;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataEntity;

interface RelationHydrator
{
    public function supports(array $relation): bool;

    public function hydrate(
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $data
    ): Closure|EntityBase|null;
}
