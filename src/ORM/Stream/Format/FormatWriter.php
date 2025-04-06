<?php

namespace ORM\Stream\Format;

/**
 * Minimal FormatWriter interface used by the StreamWrapper.
 *
 * This interface defines a method for serializing a single entity into a string representation.
 *
 * @example
 * // Example usage with a concrete implementation (e.g., JsonFormatWriter):
 * $writer = new JsonFormatWriter();
 * $serializedEntity = $writer->write($entity);
 *
 * @see \ORM\Stream\Format\JsonFormatWriter
 */
interface FormatWriter
{
    /**
     * Serializes the given entity into a string representation.
     *
     * This method converts the provided entity into the target format as a string.
     *
     * @param mixed $entity The entity to serialize.
     * @return string The serialized representation of the entity.
     *
     * @example
     * // Serialize a single entity:
     * $jsonString = $writer->write($entity);
     */
    public function write(mixed $entity): string;
}
