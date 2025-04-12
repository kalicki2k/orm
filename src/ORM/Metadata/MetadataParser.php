<?php

namespace ORM\Metadata;

use InvalidArgumentException;
use ORM\Attributes\Column;
use ORM\Attributes\Entity;
use ORM\Attributes\GeneratedValue;
use ORM\Attributes\Id;
use ORM\Attributes\JoinColumn;
use ORM\Attributes\OneToOne;
use ORM\Attributes\Table;
use ORM\Entity\EntityBase;
use ORM\Util\ReflectionCacheInstance;
use ReflectionException;
use ReflectionProperty;

class MetadataParser
{
    /**
     * @throws ReflectionException
     */
    public function extract(EntityBase $entity, bool $excludePrimaryKey = false) : array
    {
        $metadata = $this->parse($entity::class);
        $reflection = ReflectionCacheInstance::getInstance()->get($entity::class);

        $data = [];

        foreach ($metadata->getColumns() as $property => $column) {
            $isPrimary = $column['name'] === $metadata->getPrimaryKey();
            $isGenerated = $metadata->isPrimaryKeyGenerated();

            if ($excludePrimaryKey && $isPrimary && $isGenerated) {
                continue;
            }

            $value = $reflection->getProperty($property)->getValue($entity);
            $data[$column['name']] = $value;
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
            $this
                ->parseId($property, $metadataEntity)
                ->parseColumn($property, $metadataEntity)
                ->parseOneToOne($property, $metadataEntity);
        }

        return $metadataEntity;
    }

    /**
     * Verarbeitet das Id-Attribut einer Property und setzt den Primärschlüssel in der MetadataEntity.
     *
     * @param ReflectionProperty $property
     * @param MetadataEntity $metadataEntity
     *
     * @return $this
     */
    protected function parseId(ReflectionProperty $property, MetadataEntity $metadataEntity): MetadataParser
    {
        $idAttributes = $property->getAttributes(Id::class);

        if (!empty($idAttributes)) {
            $columnAttributes = $property->getAttributes(Column::class);
            $generatedValueAttributes = $property->getAttributes(GeneratedValue::class);
            $idColumnName = !empty($columnAttributes)
                ? ($columnAttributes[0]->newInstance()->name ?: $property->getName())
                : $property->getName();

            $metadataEntity->setPrimaryKey($idColumnName);
            $metadataEntity->setPrimaryKeyGenerated(!empty($generatedValueAttributes));
        }

        return $this;
    }

    /**
     * Verarbeitet das Column-Attribut einer Property.
     *
     * @param ReflectionProperty $property
     * @param MetadataEntity $metadataEntity
     *
     * @return $this
     */
    protected function parseColumn(ReflectionProperty $property, MetadataEntity $metadataEntity): MetadataParser
    {
        $columnAttributes = $property->getAttributes(Column::class);

        if (!empty($columnAttributes)) {
            /** @var Column $column */
            $column = $columnAttributes[0]->newInstance();
            $column->name ??= $property->getName();
            $metadataEntity->addColumn($column);
        }

        return $this;
    }

    /**
     * @param ReflectionProperty $property
     * @param MetadataEntity $metadataEntity
     *
     * @return $this
     */
    protected function parseOneToOne(ReflectionProperty $property, MetadataEntity $metadataEntity): MetadataParser
    {
        $oneToOneAttributes = $property->getAttributes(OneToOne::class);

        if (!empty($oneToOneAttributes)) {
            $joinColumnAttributes = $property->getAttributes(JoinColumn::class);
            $joinColumn = !empty($joinColumnAttributes)
                ? $joinColumnAttributes[0]->newInstance()
                : null;

            $metadataEntity->addRelation($property->getName(), $oneToOneAttributes[0]->newInstance(), $joinColumn);
        }

        return $this;
    }
}