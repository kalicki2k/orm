<?php

namespace ORM\Metadata;

use InvalidArgumentException;
use ORM\Attributes\Entity;
use ORM\Attributes\Table;
use ORM\Cache\InMemoryMetadataCache;
use ORM\Cache\InMemoryReflectionCache;
use ORM\Cache\MetadataCache;
use ORM\Cache\ReflectionCache;
use ORM\Entity\EntityBase;
use ORM\Metadata\AttributeHandler\ColumnAttributeHandler;
use ORM\Metadata\AttributeHandler\IdAttributeHandler;
use ORM\Metadata\AttributeHandler\MetadataAttributeHandler;
use ORM\Metadata\AttributeHandler\OneToOneAttributeHandler;
use ReflectionException;

class MetadataParser
{
    /** @var MetadataAttributeHandler[] */
    private array $handlers;

    public function __construct(
        private MetadataCache $metadataCache = new InMemoryMetadataCache(),
        private ReflectionCache $reflectionCache = new InMemoryReflectionCache(),
    ) {
        $this->handlers = [
            new IdAttributeHandler(),
            new ColumnAttributeHandler(),
            new OneToOneAttributeHandler($this),
//            new OneToManyAttributeHandler(),
        ];
    }

    public function with(MetadataCache|ReflectionCache $instance): self
    {
        $map = [
            MetadataCache::class => fn() => $this->metadataCache = $instance,
            ReflectionCache::class => fn() => $this->reflectionCache = $instance,
        ];

        foreach ($map as $interface => $apply) {
            if ($instance instanceof $interface) {
                $apply();
                return $this;
            }
        }

        throw new InvalidArgumentException("Unsupported cache instance: " . $instance::class);
    }

    public function getReflectionCache(): ReflectionCache
    {
        return $this->reflectionCache;
    }

    /**
     * Converts an entity object into an associative array of column => value.
     *
     * @throws ReflectionException
     */
    public function extract(EntityBase $entity, bool $excludePrimaryKey = false): array
    {
        $metadata = $this->parse($entity::class);
        $data = [];

        foreach ($metadata->getColumns() as $property => $column) {
            $isPrimary = $column['name'] === $metadata->getPrimaryKey();
            $isGenerated = $metadata->isPrimaryKeyGenerated();

            if ($excludePrimaryKey && $isPrimary && $isGenerated) {
                continue;
            }

            if ($this->reflectionCache->hasProperty($entity, $property)) {
                $data[$column['name']] = $this->reflectionCache
                    ->getProperty($entity, $property)
                    ->getValue($entity);
                continue;
            }

            foreach ($metadata->getRelations() as $relationProperty => $relationData) {
                $joinColumn = $relationData['joinColumn'] ?? null;

                if ($joinColumn && $joinColumn->name === $column['name']) {
                    $related = $this->reflectionCache->getValue($entity, $relationProperty);

                    if ($related instanceof \Closure) {
                        $related = $related();
                        $this->reflectionCache->setValue($entity, $relationProperty, $related);
                    }

                    if ($related instanceof EntityBase) {
                        $relatedMetadata = $this->parse($related::class);
                        $data[$joinColumn->name] = $this->reflectionCache
                            ->getValue($related, $relatedMetadata->getPrimaryKey());
                    }

                    break;
                }
            }
        }

        return $data;
    }

    /**
     * Parses metadata for an entity class – with caching.
     *
     * @throws ReflectionException
     */
    public function parse(string $entityName): MetadataEntity
    {
        if ($cached = $this->metadataCache->get($entityName)) {
            return $cached;
        }

        $reflection = $this->reflectionCache->getClass($entityName);
        $entityAttributes = $reflection->getAttributes(Entity::class);
        $tableAttributes = $reflection->getAttributes(Table::class);

        if (empty($entityAttributes) || empty($tableAttributes)) {
            throw new InvalidArgumentException("The class $entityName is not marked as an entity.");
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

        $this->metadataCache->set($entityName, $metadataEntity);
        return $metadataEntity;
    }
}
