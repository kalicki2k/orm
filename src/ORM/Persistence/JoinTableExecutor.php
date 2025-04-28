<?php

namespace ORM\Persistence;

use ORM\Drivers\DatabaseDriver;
use ORM\Entity\EntityBase;
use ORM\Metadata\MetadataParser;
use ORM\Query\Expression;
use ORM\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use ReflectionException;
use RuntimeException;

final readonly class JoinTableExecutor
{
    public function __construct(
        private DatabaseDriver $databaseDriver,
        private MetadataParser $metadataParser,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function execute(JoinTableSchedule $schedule): void
    {
        foreach ($schedule->getScheduledForDelete() as $entry) {
            $this->executeDelete($entry["entity"], $entry["property"], $entry["relatedEntity"]);
        }

        foreach ($schedule->getScheduledForInsert() as $entry) {
            $this->executeInsert($entry["entity"], $entry["property"], $entry["relatedEntity"]);
        }
    }

    /**
     * @throws ReflectionException
     */
    private function getRelationData(EntityBase $owner, string $property, EntityBase $related): array
    {
        $ownerMetadata = $this->metadataParser->parse($owner::class);
        $relatedMetadata = $this->metadataParser->parse($related::class);
        $relationData = $ownerMetadata->getRelations()[$property];
        $joinTable = $relationData["joinTable"];

        $ownerPrimaryKey = $ownerMetadata->getPrimaryKey();
        $relatedPrimaryKey = $relatedMetadata->getPrimaryKey();

        $ownerId = $this->metadataParser->extract($owner)[$ownerPrimaryKey] ?? null;
        $relatedId = $this->metadataParser->extract($related)[$relatedPrimaryKey] ?? null;

        if ($ownerId === null || $relatedId === null) {
            throw new RuntimeException("Cannot process relation without identifiers.");
        }

        return [
            "joinTable" => $joinTable,
            "ownerId" => $ownerId,
            "relatedId" => $relatedId
        ];
    }


    /**
     * @throws ReflectionException
     */
    private function executeInsert(EntityBase $owner, string $property, EntityBase $related): void
    {
        $data = $this->getRelationData($owner, $property, $related);
        $joinTable = $data["joinTable"];

        new QueryBuilder($this->databaseDriver, $this->logger)
            ->insert()
            ->table($joinTable->name)
            ->values([
                $joinTable->joinColumn => $data["ownerId"],
                $joinTable->inverseJoinColumn => $data["relatedId"]
            ])
            ->execute();
    }

    /**
     * @throws ReflectionException
     */
    private function executeDelete(EntityBase $owner, string $property, EntityBase $related): void
    {
        $data = $this->getRelationData($owner, $property, $related);
        $joinTable = $data["joinTable"];

        new QueryBuilder($this->databaseDriver, $this->logger)
            ->delete()
            ->table($joinTable->name)
            ->where(Expression::and()
                ->andEq($joinTable->joinColumn, $data["ownerId"])
                ->andEq($joinTable->inverseJoinColumn, $data["relatedId"]))
            ->execute();
    }
}
