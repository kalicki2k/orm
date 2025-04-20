<?php

namespace ORM;

use ArrayIterator;
use Countable;
use IteratorAggregate;

class Collection implements IteratorAggregate, Countable
{
    private array $items = [];

    public function add(object $item): bool
    {
        if (!$this->contains($item)) {
            $this->items[] = $item;
            return true;
        }
        return false;
    }

    public function remove(object $item): bool
    {
        $key = array_search($item, $this->items, true);
        if ($key !== false) {
            unset($this->items[$key]);
            $this->items = array_values($this->items);
            return true;
        }
        return false;
    }

    public function contains(object $item): bool
    {
        return in_array($item, $this->items, true);
    }

    public function toArray(): array
    {
        return $this->items;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
