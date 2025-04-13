<?php

namespace ORM\Entity;

use JsonSerializable;

abstract class EntityBase implements JsonSerializable
{
    private ?array $__originalData = null;

    protected bool $__persisted = false;

    public function __markPersisted(array $data): void
    {
        $this->__persisted = true;
        if (!empty($data))
        {
            $this->__originalData = $data;
        }
    }

    public function __isPersisted(): bool
    {
        return $this->__persisted;
    }

    public function __takeSnapshot(array $data): void
    {
        $this->__originalData = $data;
    }

    public function __isDirty(array $data): bool
    {
        return $this->__originalData !== $data;
    }

//    public function __getChanges(array $data): array
//    {
//        if ($this->__originalData === null) return [];
//
//        return array_diff_assoc($data, $this->__originalData);
//    }

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