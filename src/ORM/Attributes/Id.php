<?php

namespace ORM\Attributes;

use Attribute;

/**
 * Marks a property as the primary key of the entity.
 *
 * This attribute should be used on a property that represents the unique identifier of an entity.
 * Usually used together with #[GeneratedValue] and #[Column].
 *
 * @example
 * ```php
 * #[Id]
 * #[GeneratedValue]
 * #[Column(type: "int")]
 * private int $id;
 * ```
 *
 * @see Column
 * @see GeneratedValue
 * @see \ORM\MetadataParser
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Id {}
