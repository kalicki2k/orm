<?php

namespace ORM\Stream\Format;

use InvalidArgumentException;
use ORM\Entity\EntityBase;
use SimpleXMLElement;

/**
 * XmlFormatWriter serializes entities into XML format.
 *
 * This class implements the FormatWriter interface to convert an entity's public properties
 * into an XML representation. It uses SimpleXMLElement to build the XML document.
 *
 * @example
 * $writer = new XmlFormatWriter();
 * $xmlString = $writer->write($entity);
 * echo $xmlString;
 *
 * @see \ORM\Stream\Format\FormatWriter
 */
class XmlFormatWriter implements FormatWriter
{
    /**
     * Serializes the given entity into an XML string.
     *
     * This method converts the public properties of an entity into an XML representation.
     * The entity must be an object. The method creates a root element and appends the
     * entity's properties as child elements. For arrays or nested structures, a recursive
     * conversion is performed.
     *
     * @param mixed $entity The entity to serialize.
     * @return string The XML representation of the entity.
     *
     * @throws InvalidArgumentException if the entity is not an object.
     *
     * @example
     * // Serialize an entity:
     * $xmlString = $writer->write($entity);
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

        $xml = new SimpleXMLElement('<entity/>');
        $this->arrayToXml($data, $xml);

        return $xml->asXML();
    }

    /**
     * Recursively converts an array to XML elements.
     *
     * This helper method iterates over the provided data array and adds each element as a child
     * to the provided SimpleXMLElement. Numeric keys are converted to a default tag name ("item").
     *
     * @param array $data The data to convert.
     * @param SimpleXMLElement $xml The XML element to append data to.
     * @return void
     *
     * @example
     * // Internally used to build nested XML structures.
     * $this->arrayToXml($data, $xml);
     */
    protected function arrayToXml(array $data, SimpleXMLElement &$xml): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item';
            }
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }
}
