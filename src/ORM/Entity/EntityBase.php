<?php

namespace ORM\Entity;

use JsonSerializable;

abstract class EntityBase implements JsonSerializable
{
    protected bool $__persisted = false;

    public function __markPersisted(): void
    {
        $this->__persisted = true;
    }

    public function __isPersisted(): bool
    {
        return $this->__persisted;
    }

    /**
     * Override this in your Entity to expose the actual ID.
     */
    public function getId(): int|string|null
    {
        return null;
    }

    /**
     * Basic default serialization. Should be overridden in entities.
     */
    public function jsonSerialize(): mixed
    {
        return get_object_vars($this);
    }
}