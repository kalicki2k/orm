<?php

namespace ORM\Hydration;

use DateMalformedStringException;
use DateTimeImmutable;
use ORM\Cache\ReflectionCache;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataEntity;
use ReflectionException;

final readonly class ColumnHydrator
{
    public function __construct(private ReflectionCache $reflectionCache) {}

    /**
     * Hydrates primitive column values from the result set into the entity.
     *
     * Uses alias mapping (e.g., `user_email`) to resolve values.
     * Skips JoinColumns (like `profile_id`) if no matching property exists,
     * since they will be handled later in relation hydration (Lazy/Eager).
     *
     * @param EntityBase $entity The entity being hydrated.
     * @param MetadataEntity $metadata The metadata describing the entity.
     * @param array $row The aliased SQL result row.
     *
     * @throws DateMalformedStringException
     * @throws ReflectionException
     */
    public function hydrate(EntityBase $entity, MetadataEntity $metadata, array $row): void
    {
        foreach ($metadata->getColumns() as $property => $column) {
            $alias = sprintf("%s_%s", $metadata->getAlias(), $column["name"]);

            if (!array_key_exists($alias, $row) || !$this->reflectionCache->hasProperty($entity, $property)) {
                continue;
            }

            $value = $this->convert($row[$alias], $column["attributes"]->type);
            $this->reflectionCache->getProperty($entity, $property)->setValue($entity, $value);
        }
    }

    /**
     * Converts a raw database value to its PHP representation based on the expected column type.
     *
     * This method is used during column hydration to normalize SQL values
     * into proper native PHP types (e.g. DateTime, int, string).
     *
     * @param mixed $value The raw value from the database.
     * @param string|null $type Optional column type hint (e.g. "int", "datetime").
     *
     * @return mixed The hydrated PHP value.
     *
     * @throws DateMalformedStringException
     */
    private function convert(mixed $value, ?string $type = null): mixed
    {
        if ($type === null) {
            return $value;
        }

        return match (strtolower($type)) {
            "int", "integer" => (int) $value,
            "float", "double" => (float) $value,
            "bool", "boolean" => (bool) $value,
            "datetime" => new DateTimeImmutable($value),
            "json" => json_decode($value, true),
            default => $value,
        };
    }
}