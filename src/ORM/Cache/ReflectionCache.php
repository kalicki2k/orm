<?php

namespace ORM\Cache;

use ORM\Entity\EntityBase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

interface ReflectionCache
{
    public function getClass(EntityBase|string $class): ReflectionClass;
    public function getProperty(EntityBase $class, string $property): ReflectionProperty;
    public function getType(EntityBase $class, string $property): ?ReflectionNamedType;
    public function getValue(EntityBase $class, string $property): mixed;
    public function setValue(EntityBase $class, string $property, mixed $value): void;
    public function isInitialized(EntityBase $class, string $property): bool;
    public function clear(string $class): void;
}