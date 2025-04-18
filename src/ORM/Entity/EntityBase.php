<?php

namespace ORM\Entity;

use JsonSerializable;
use LogicException;

abstract class EntityBase implements JsonSerializable
{
    private ?array $__originalData = null;

    public function __markPersisted(array $data): void
    {
        $this->__originalData = $data;
    }

    public function __isPersisted(): bool
    {
        return !empty($this->__originalData);
    }

    public function __takeSnapshot(array $data): void
    {
        $this->__originalData = $data;
    }

    public function __isDirty(array $data): bool
    {
        return $this->__originalData !== $data;
    }

    /**
     * Basic default serialization. Should be overridden in entities.
     */
    public function jsonSerialize(): mixed
    {
        throw new LogicException("Entity '". static::class ."' must implement its own jsonSerialize() method.");
    }
}