<?php

namespace ORM\Util;

use ORM\Entity\EntityBase;
use ReflectionClass;
use ReflectionException;

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
    private array $cache = [];

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
        if (!isset($this->cache[$className])) {
            $this->cache[$className] = new ReflectionClass($className);
        }
        return $this->cache[$className];
    }
}
