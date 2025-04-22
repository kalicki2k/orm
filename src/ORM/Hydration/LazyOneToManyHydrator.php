<?php

namespace ORM\Hydration;

use Closure;
use ORM\Attributes\OneToMany;
use ORM\Collection;
use ORM\Entity\EntityBase;
use ORM\Entity\EntityManager;
use ORM\Entity\Type\FetchType;
use ORM\Metadata\MetadataEntity;

/**
 * Hydrates #[OneToMany] relations using lazy-loading.
 *
 * This hydrator generates a Closure for OneToMany relations marked as FetchType::Lazy.
 * The Closure is stored in the entity property and executed on first access.
 * It queries the related table using EntityManager::findAll() with the appropriate foreign key.
 *
 * @example
 * #[OneToMany(entity: Post::class, mappedBy: "user", fetch: FetchType::Lazy)]
 * private Collection $posts;
 *
 * // Accessing $user->getPosts() will invoke the closure and perform the query.
 *
 * @package ORM\Hydration
 *
 * @see OneToMany
 * @see EntityManager::findAll
 * @see EntityHydrator
 */
final readonly class LazyOneToManyHydrator implements RelationHydrator
{
    /**
     * LazyOneToManyHydrator constructor.
     *
     * @param EntityManager $entityManager The entity manager used to perform deferred fetches.
     */
    public function __construct(private EntityManager $entityManager) {}

    /**
     * Checks whether this hydrator supports the given relation.
     *
     * This hydrator handles relations of type #[OneToMany] with FetchType::Lazy.
     * It is used during relation hydration to determine which hydrator is responsible
     * for processing a given relation declaration.
     *
     * @param array $relation The parsed relation metadata including the attribute instance.
     * @return bool True if the relation is a lazy OneToMany, false otherwise.
     */
    public function supports(array $relation): bool
    {
        return $relation['relation'] instanceof OneToMany
            && $relation['relation']->fetch === FetchType::Lazy;
    }

    /**
     * Returns a deferred loader (Closure) for a lazy OneToMany relationship.
     *
     * This Closure is stored in the parent entity's property and executed only when accessed.
     * It resolves the foreign key from the current entity row, and performs a secondary query
     * to fetch all related child entities using the `mappedBy` property and JoinColumn definition.
     *
     * Example:
     *  - Parent entity: User
     *  - Property: posts
     *  - mappedBy: "user" on Post entity
     *  - Result: SELECT * FROM posts WHERE user_id = :id
     *
     * @param MetadataEntity $parentMetadata The metadata for the owning (parent) entity.
     * @param string $property The property name in the parent entity (e.g. "posts").
     * @param array $relation The relation config including `mappedBy` and target entity.
     * @param array $row The current SQL result row from the parent entity query.
     * @return Closure Returns a Closure that lazily loads the child collection when called.
     *
     * @see Collection
     */
    public function hydrate(
        EntityBase $parentEntity,
        MetadataEntity $parentMetadata,
        string $property,
        array $relation,
        array $row
    ): Closure {
        $targetEntity = $relation['relation']->entity;
        $mappedBy = $relation['relation']->mappedBy;

        return function () use ($parentMetadata, $targetEntity, $mappedBy, $row) {
            $id = $row["{$parentMetadata->getAlias()}_{$parentMetadata->getPrimaryKey()}"] ?? null;

            if ($id === null) {
                return new Collection();
            }

            $targetMetadata = $this->entityManager->getMetadata($targetEntity);
            $fkColumnName = $targetMetadata->getRelations()[$mappedBy]['joinColumn']->name;

            return $this->entityManager->findAll($targetEntity, [$fkColumnName => $id]);
        };
    }
}
