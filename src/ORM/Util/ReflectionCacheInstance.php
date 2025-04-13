<?php

namespace ORM\Util;

use ORM\Entity\EntityBase;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * A singleton utility for caching ReflectionClass instances.
 *
 * This class provides a performance-optimized way to reuse reflection metadata
 * by avoiding repeated instantiation of ReflectionClass objects for the same class.
 */
final class ReflectionCacheInstance
{
    /**
     * Internal cache indexed by class name.
     *
     * @var array<string, ReflectionClass>
     */
    private array $classCache = [];

    private array $propertyCache = [];
    private array $typeCache = [];

    /**
     * The singleton instance of ReflectionCacheInstance.
     */
    private static ?self $instance = null;

    /**
     * Private constructor to enforce singleton usage.
     */
    private function __construct() {}

    /**
     * Returns the singleton instance of ReflectionCacheInstance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Returns a cached ReflectionClass instance for a given class name or object.
     *
     * @param EntityBase|string $class The class name or an EntityBase instance.
     * @return ReflectionClass
     * @throws ReflectionException If the class does not exist.
     */
    public function get(EntityBase|string $class): ReflectionClass
    {
        $className = $class instanceof EntityBase ? $class::class : $class;
        if (!isset($this->classCache[$className])) {
            $this->classCache[$className] = new ReflectionClass($className);
        }
        return $this->classCache[$className];
    }

    /**
     * @throws ReflectionException
     */
    public function getProperty(string $class, string $property): ReflectionProperty
    {
        if (!isset($this->propertyCache[$class][$property])) {
            $reflectionClass = $this->get($class);
            $reflectionProperty = $reflectionClass->getProperty($property);
            $this->propertyCache[$class][$property] = $reflectionProperty;
        }

        return $this->propertyCache[$class][$property];
    }

    /**
     * @throws ReflectionException
     */
    public function getType(string $class, string $property): ?ReflectionNamedType
    {
        if (!isset($this->typeCache[$class][$property])) {
            $prop = $this->getProperty($class, $property);
            $this->typeCache[$class][$property] = $prop->getType() instanceof ReflectionNamedType
                ? $prop->getType()
                : null;
        }

        return $this->typeCache[$class][$property];
    }

    /**
     * @throws ReflectionException
     */
    public function getValue(object $object, string $property): mixed
    {
        return $this->getProperty($object::class, $property)->getValue($object);
    }

    /**
     * @throws ReflectionException
     */
    public function setValue(object $object, string $property, mixed $value): void
    {
        $this->getProperty($object::class, $property)->setValue($object, $value);
    }
}
