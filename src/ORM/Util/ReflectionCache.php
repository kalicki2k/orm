<?php

namespace ORM\Util;

use ReflectionClass;
use ReflectionException;

/**
 * Class ReflectionCache
 *
 * Provides a static in-memory cache for ReflectionClass instances
 * to avoid redundant reflection operations and improve performance.
 */
class ReflectionCache
{
    /**
     * Internal cache indexed by class name.
     *
     * @var array<string, ReflectionClass>
     */
    private static array $reflectionCache = [];

    /**
     * Returns a cached ReflectionClass instance for a class or object.
     *
     * @param object|string $class The class name or an object instance
     *
     * @return ReflectionClass
     *
     * @throws ReflectionException If the class does not exist
     *
     * @todo https://php.watch/articles/practical-weakmap
     *       https://symfony.com/blog/revisiting-lazy-loading-proxies-in-php
     *       https://itsimiro.medium.com/lazy-objects-in-php-8-4-a-new-era-of-efficient-object-handling-ce4832a1143c
     */

    public static function get(object|string $class): ReflectionClass
    {
        $className = is_object($class) ? get_class($class) : $class;

        return self::$reflectionCache[$className] ??= new ReflectionClass($className);
    }
}