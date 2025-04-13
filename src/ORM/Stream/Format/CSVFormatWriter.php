<?php

namespace ORM\Stream\Format;

use InvalidArgumentException;
use ORM\Entity\EntityBase;

/**
 * CSVFormatWriter serializes entities into CSV format.
 *
 * This class provides methods to convert a single entity (object) or a collection of entities
 * into CSV format. Each entity's public properties are used to generate a CSV row.
 *
 * @example
 * // Example usage:
 * $csvWriter = new CSVFormatWriter();
 * $csvRow = $csvWriter->write($entity);
 * echo $csvRow;
 */
class CSVFormatWriter implements FormatWriter
{
    /**
     * Indicates whether the CSV header has already been written.
     *
     * The header (the keys of the first entity) is output only once per instance.
     *
     * @var bool
     */
    protected bool $headerWritten = false;

    /**
     * Serializes a single entity into a CSV row string.
     *
     * This method converts the public properties of the given object into an associative array
     * and then produces a CSV-formatted row. On the first call, it emits a header row containing
     * the field names, followed by the CSV row. On subsequent calls, only the CSV row is returned.
     *
     * @param mixed $entity The entity to serialize.
     * @return string The CSV representation of the entity, including the header on the first call.
     *
     * @throws InvalidArgumentException if the entity is not an object.
     *
     * @example
     * // Given an entity with properties "id", "username", "email":
     * // First call might return: "id,username,email\n1,alice,alice@example.com"
     * // Subsequent calls return only the CSV row.
     */
    public function write(mixed $entity): string
    {
        if (!is_object($entity)) {
            throw new InvalidArgumentException("Entity must be an object.");
        }

        if (!is_subclass_of($entity, EntityBase::class)) {
            throw new InvalidArgumentException("Entity must extend EntityBase.");
        }

        $data = $entity->jsonSerialize();

        if (!is_array($data) || empty($data)) {
            return '';
        }

        $csvRow = '';

        if (!$this->headerWritten) {
            $header = array_keys($data);
            $csvRow .= $this->arrayToCsvRow($header) . PHP_EOL;
            $this->headerWritten = true;
        }

        $csvRow .= $this->arrayToCsvRow($data);
        return $csvRow;
    }

    /**
     * Converts an array of values into a CSV row string.
     *
     * This helper method converts the provided array of values into a CSV-formatted row.
     * Each field is inspected for the presence of the delimiter, enclosure, or any line break
     * characters (both "\n" and "\r"). If such characters are detected, the field is enclosed
     * with the enclosure character and any existing enclosure characters within the field are doubled.
     *
     * @param array  $row The array of values to convert.
     * @param string $delimiter The field delimiter (default is a comma).
     * @param string $enclosure The enclosure character (default is a double quote).
     * @return string The CSV row as a string.
     *
     * @example
     * // Convert an array to a CSV row:
     * $csvRow = $csvWriter->arrayToCsvRow(['id', 'username', 'email']);
     */
    protected function arrayToCsvRow(array $row, string $delimiter = ',', string $enclosure = '"'): string
    {
        $escapedFields = array_map(function($field) use ($delimiter, $enclosure) {
            $field = (string) $field;

            if (str_contains($field, $delimiter) || str_contains($field, $enclosure) || str_contains($field, "\n") || str_contains($field, "\r")) {
                $field = str_replace($enclosure, $enclosure . $enclosure, $field);
                return $enclosure . $field . $enclosure;
            }

            return $field;
        }, $row);

        return implode($delimiter, $escapedFields);
    }
}
