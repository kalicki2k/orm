<?php

namespace ORM\Cache;

use ORM\Entity\EntityBase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionException;

/**
 * Default implementation of ReflectionCache using in-memory storage.
 *
 * Caches ReflectionClass and ReflectionProperty objects to avoid repeated reflection.
 * This improves performance during hydration, change-tracking and runtime analysis.
 */
class InMemoryReflectionCache implements ReflectionCache
{
    private array $classCache = [];
    private array $propertyCache = [];
    private array $typeCache = [];

    /**
     * @throws ReflectionException
     */
    public function getClass(EntityBase|string $class): ReflectionClass
    {
        $className = $class instanceof EntityBase ? $class::class : $class;
        return $this->classCache[$className] ??= new ReflectionClass($className);
    }

    /**
     * @throws ReflectionException
     */
    public function getProperty(EntityBase $class, string $property): ReflectionProperty
    {
        return $this->propertyCache[$class::class][$property]
            ??= $this->getClass($class)->getProperty($property);
    }

    /**
     * @throws ReflectionException
     */
    public function getType(EntityBase $class, string $property): ?ReflectionNamedType
    {
        return $this->typeCache[$class::class][$property]
            ??= $this->getProperty($class, $property)->getType() instanceof ReflectionNamedType
                ? $this->getProperty($class, $property)->getType()
                : null;
    }

    /**
     * @throws ReflectionException
     */
    public function getValue(EntityBase $class, string $property): mixed
    {
        return $this->getProperty($class, $property)->getValue($class);
    }

    /**
     * @throws ReflectionException
     */
    public function setValue(EntityBase $class, string $property, mixed $value): void
    {
        $this->getProperty($class, $property)->setValue($class, $value);
    }

    /**
     * @throws ReflectionException
     */
    public function isInitialized(EntityBase $class, string $property): bool
    {
        return $this->getProperty($class, $property)->isInitialized($class);
    }

    /**
     * @throws ReflectionException
     */
    public function hasProperty(EntityBase $class, string $property): bool
    {
        $className = $class::class;

        if (!isset($this->classCache[$className])) {
            $this->classCache[$className] = new ReflectionClass($className);
        }

        return $this->classCache[$className]->hasProperty($property);
    }

    public function clear(string $class): void
    {
        unset($this->classCache[$class], $this->propertyCache[$class]);
    }
}
