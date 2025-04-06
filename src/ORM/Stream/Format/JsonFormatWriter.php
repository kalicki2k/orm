<?php

namespace ORM\Stream\Format;

use JsonSerializable;
use InvalidArgumentException;

/**
 * JsonFormatWriter serializes entities into JSON format.
 *
 * This class implements the FormatWriter interface to convert entities into
 * a JSON string using json_encode. It requires that the entity implements
 * the JsonSerializable interface.
 *
 * @example
 * // Example usage:
 * $writer = new JsonFormatWriter();
 * $jsonString = $writer->write($entity);
 * echo $jsonString;
 *
 * @see \ORM\Stream\Format\FormatWriter
 */
class JsonFormatWriter implements FormatWriter
{
    /**
     * Serializes the given entity into a JSON string.
     *
     * This is the core method that performs the serialization. It checks if the
     * entity implements JsonSerializable and then returns its JSON representation.
     *
     * @param mixed $entity The entity to serialize.
     * @return string The JSON representation of the entity.
     *
     * @throws InvalidArgumentException if the entity does not implement JsonSerializable.
     *
     * @example
     * // Serialize an entity:
     * $jsonString = $writer->write($entity);
     */
    public function write(mixed $entity): string
    {
        if (!$entity instanceof JsonSerializable) {
            throw new InvalidArgumentException("Entity must implement JsonSerializable");
        }
        return json_encode($entity, JSON_UNESCAPED_UNICODE);
    }
}