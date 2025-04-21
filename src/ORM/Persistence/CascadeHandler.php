<?php

namespace ORM\Persistence;

use ORM\Entity\EntityBase;
use ORM\Entity\Type\CascadeType;
use ORM\Metadata\MetadataParser;
use ORM\UnitOfWork;
use ReflectionException;
use Traversable;

final readonly class CascadeHandler
{
    public function __construct(
        private MetadataParser $metadataParser,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function handle(EntityBase $entity, CascadeType $action): void
    {
        $metadata = $this->metadataParser->parse($entity::class);
        $reflection = $this->metadataParser->getReflectionCache();

        foreach ($metadata->getRelations() as $property => $relationInfo) {
            $reflectionProperty = $reflection->getProperty($entity, $property);

            if (!$reflectionProperty->isInitialized($entity)) {
                continue;
            }

            $cascade = $relationInfo["relation"]->cascade ?? [];
            $relatedEntity = $reflection->getValue($entity, $property);

            if ($relatedEntity instanceof EntityBase) {
                if (!in_array($action, $cascade, true)) {
                    continue;
                }

                match ($action) {
                    CascadeType::Persist => $this->unitOfWork->scheduleForInsert($relatedEntity),
                    CascadeType::Remove => $this->unitOfWork->scheduleForDelete($relatedEntity),
                };

                continue;
            }

            if ($relatedEntity instanceof Traversable) {
                foreach ($relatedEntity as $item) {
                    if ($item instanceof EntityBase && in_array($action, $cascade, true)) {
                        match ($action) {
                            CascadeType::Persist => $this->unitOfWork->scheduleForInsert($item),
                            CascadeType::Remove => $this->unitOfWork->scheduleForDelete($item),
                        };
                    }
                }
            }
        }
    }
}
