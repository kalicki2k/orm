<?php

namespace ORM\Metadata;

use InvalidArgumentException;
use ORM\Attributes\Entity;
use ORM\Attributes\Table;
use ORM\Entity\EntityBase;
use ORM\Metadata\AttributeHandler\ColumnAttributeHandler;
use ORM\Metadata\AttributeHandler\IdAttributeHandler;
use ORM\Metadata\AttributeHandler\MetadataAttributeHandler;
use ORM\Metadata\AttributeHandler\OneToOneAttributeHandler;
use ORM\Util\ReflectionCacheInstance;
use ReflectionException;

class MetadataParser
{
    /** @var MetadataAttributeHandler[] */
    private array $handlers;
    public function __construct()
    {
        $this->handlers = [
            new IdAttributeHandler(),
            new ColumnAttributeHandler(),
            new OneToOneAttributeHandler(),
        ];
    }

    /**
     * @throws ReflectionException
     */
    public function extract(EntityBase $entity, bool $excludePrimaryKey = false) : array
    {
        $metadata = $this->parse($entity::class);
        $reflection = ReflectionCacheInstance::getInstance();

        $data = [];

        foreach ($metadata->getColumns() as $property => $column) {
            $isPrimary = $column['name'] === $metadata->getPrimaryKey();
            $isGenerated = $metadata->isPrimaryKeyGenerated();

            if ($excludePrimaryKey && $isPrimary && $isGenerated) {
                continue;
            }

            $data[$column['name']] = $reflection->getValue($entity, $property);
        }

        return $data;
    }

    /**
     * @throws ReflectionException
     */
    public function parse(string $entityName): MetadataEntity
    {
        $reflection = ReflectionCacheInstance::getInstance()->get($entityName);
        $entityAttributes = $reflection->getAttributes(Entity::class);
        $tableAttributes = $reflection->getAttributes(Table::class);

        if (empty($entityAttributes) || empty($tableAttributes)) {
            throw new InvalidArgumentException("The class {$entityName} is not marked as an entity.");
        }

        $metadataEntity = new MetadataEntity($entityName);
        $metadataEntity
            ->setTable($tableAttributes[0]->newInstance()->name)
            ->setAlias(strtolower($reflection->getShortName()));

        foreach ($reflection->getProperties() as $property) {
            foreach ($this->handlers as $handler) {
                if ($handler->supports($property)) {
                    $handler->build($property, $metadataEntity);
                }
            }
        }

        return $metadataEntity;
    }
}