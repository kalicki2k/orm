<?php

namespace ORM\Hydration;

use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataEntity;

interface Hydrator {
    public function hydrate(MetadataEntity $metadata, array $row): EntityBase;
    public function hydrateRelations(EntityBase $entity, MetadataEntity $metadata, array $row): void;
}
