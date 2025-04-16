<?php

namespace ORM\Persistence;

use ORM\Entity\EntityBase;
use ORM\Entity\Type\CascadeType;
use ORM\Metadata\MetadataParser;
use ORM\Util\ReflectionCacheInstance;
use ORM\UnitOfWork;
use ReflectionException;

final class CascadeHandler
{
    public function __construct(
        private readonly MetadataParser $metadataParser,
        private readonly UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function handle(EntityBase $entity, CascadeType $action): void
    {
        $metadata = $this->metadataParser->parse($entity::class);
        $reflection = ReflectionCacheInstance::getInstance();

        foreach ($metadata->getRelations() as $property => $relationInfo) {
            $reflectionProperty = $reflection->getProperty($entity, $property);

            if (!$reflectionProperty->isInitialized($entity)) {
                continue;
            }

            $cascade = $relationInfo["relation"]->cascade ?? [];
            $relatedEntity = $reflection->getValue($entity, $property);

            if (!($relatedEntity instanceof EntityBase)) {
                continue;
            }

            if (!in_array($action, $cascade, true)) {
                continue;
            }

            match ($action) {
                CascadeType::Persist => $this->unitOfWork->scheduleForInsert($relatedEntity),
                CascadeType::Remove => $this->unitOfWork->scheduleForDelete($relatedEntity),
            };
        }
    }
}
