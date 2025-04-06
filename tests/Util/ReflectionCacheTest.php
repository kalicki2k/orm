<?php

namespace Tests\Util;

use ORM\Util\ReflectionCache;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Dummy entity used to test reflection caching.
 */
class DummyEntity
{
    public int $id;
}

/**
 * Unit tests for the ReflectionCache utility class.
 */
class ReflectionCacheTest extends TestCase
{
    /**
     * Ensures that a ReflectionClass instance is returned
     * when passing a fully qualified class name.
     */
    public function testReturnsReflectionClassFromClassName(): void
    {
        $reflection = ReflectionCache::get(DummyEntity::class);

        $this->assertInstanceOf(ReflectionClass::class, $reflection);
        $this->assertEquals(DummyEntity::class, $reflection->getName());
    }

    /**
     * Ensures that a ReflectionClass instance is returned
     * when passing an actual object instance.
     */
    public function testReturnsReflectionClassFromObject(): void
    {
        $instance = new DummyEntity();
        $reflection = ReflectionCache::get($instance);

        $this->assertInstanceOf(ReflectionClass::class, $reflection);
        $this->assertEquals(DummyEntity::class, $reflection->getName());
    }

    /**
     * Ensures that the same ReflectionClass instance is reused
     * from the cache (i.e., uses object identity).
     */
    public function testReturnsCachedInstance(): void
    {
        $first = ReflectionCache::get(DummyEntity::class);
        $second = ReflectionCache::get(DummyEntity::class);

        $this->assertSame($first, $second);
    }
}
