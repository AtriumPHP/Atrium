<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;

/**
 * @internal resolves a {@see Relation} into an immutable {@see RelationDescriptor}:
 * looks the target resource up in the registry (REL-20: must be registered),
 * derives its entity class + identifier field, fills the record-title default,
 * and validates that the keys required by the kind are present
 */
final readonly class RelationResolver
{
    public function __construct(private ResourceRegistry $registry)
    {
    }

    public function resolve(Relation $relation, string $parentIdField): RelationDescriptor
    {
        $target = $this->targetResource($relation);

        $childIdField = $target->getIdentifierField();
        $recordTitle = $relation->getRecordTitle();
        $recordTitleAttribute = \is_string($recordTitle) ? $recordTitle : $childIdField;

        $kind = $relation->getKind();
        if (RelationKind::OneToMany === $kind && null === $relation->getForeignKey()) {
            throw new \InvalidArgumentException(\sprintf('One-to-many relation "%s" requires foreignKey().', $relation->getName()));
        }
        if (RelationKind::ManyToMany === $kind
            && (null === $relation->getPivotTable() || null === $relation->getPivotParentKey() || null === $relation->getPivotRelatedKey())) {
            throw new \InvalidArgumentException(\sprintf('Many-to-many relation "%s" requires pivotTable() and pivotKeys().', $relation->getName()));
        }

        return new RelationDescriptor(
            name: $relation->getName(),
            kind: $kind,
            childEntityClass: $target->getEntityClass(),
            childIdField: $childIdField,
            parentIdField: $parentIdField,
            recordTitleAttribute: $recordTitleAttribute,
            foreignKey: $relation->getForeignKey(),
            pivotTable: $relation->getPivotTable(),
            pivotParentKey: $relation->getPivotParentKey(),
            pivotRelatedKey: $relation->getPivotRelatedKey(),
            pivotColumns: $relation->getPivotColumns(),
        );
    }

    /**
     * Resolve the target resource (for the manager to reuse its table/form/auth).
     * Throws when the target is not registered in the panel (REL-20).
     */
    public function targetResource(Relation $relation): AdminResource
    {
        $targetClass = $relation->getTargetClass();

        try {
            return $this->registry->getByClass($targetClass);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(\sprintf('Relation "%s" targets resource "%s" which is not registered in the panel.', $relation->getName(), $targetClass), 0, $e);
        }
    }
}
