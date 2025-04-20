<?php

namespace ORM\Cache;

use ORM\Entity\EntityBase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionException;

/**
 * Caches reflection metadata for entities to improve performance.
 *
 * This interface abstracts access to PHP Reflection data (classes, properties, types)
 * to avoid repeated reflection overhead and to enable lazy-safe operations.
 */
interface ReflectionCache
{
    /**
     * Returns the ReflectionClass for a given entity or class name.
     *
     * @param EntityBase|string $class
     * @return ReflectionClass
     * @throws ReflectionException
     */
    public function getClass(EntityBase|string $class): ReflectionClass;

    /**
     * Returns the ReflectionProperty for a given property.
     *
     * @param EntityBase $class
     * @param string $property
     * @return ReflectionProperty
     * @throws ReflectionException
     */
    public function getProperty(EntityBase $class, string $property): ReflectionProperty;

    /**
     * Returns the property type (if declared) for a property.
     *
     * @param EntityBase $class
     * @param string $property
     * @return ReflectionNamedType|null
     * @throws ReflectionException
     */
    public function getType(EntityBase $class, string $property): ?ReflectionNamedType;

    /**
     * Returns the current value of a property.
     *
     * @param EntityBase $class
     * @param string $property
     * @return mixed
     * @throws ReflectionException
     */
    public function getValue(EntityBase $class, string $property): mixed;

    /**
     * Sets a value to a property.
     *
     * @param EntityBase $class
     * @param string $property
     * @param mixed $value
     * @throws ReflectionException
     */
    public function setValue(EntityBase $class, string $property, mixed $value): void;

    /**
     * Checks if a property is initialized.
     *
     * @param EntityBase $class
     * @param string $property
     * @return bool
     * @throws ReflectionException
     */
    public function isInitialized(EntityBase $class, string $property): bool;

    /**
     * Checks if a class has the given property.
     *
     * @param EntityBase $class
     * @param string $property
     * @return bool
     */
    public function hasProperty(EntityBase $class, string $property): bool;

    /**
     * Clears the cache for the given class.
     *
     * @param string $class
     */
    public function clear(string $class): void;
}
