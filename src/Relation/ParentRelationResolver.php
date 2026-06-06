<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;

/**
 * @internal validates and resolves a child resource's {@see AdminResource::parent()}
 * declaration on first use (REL-20, lazy — mirroring {@see RelationResolver} and the
 * controller's slug-collision assertion; no compiler pass). Asserts: the parent is
 * registered, it declares the named relationship, that relation is one-to-many, and
 * its foreignKey equals the child's declared foreignKey.
 */
final readonly class ParentRelationResolver
{
    public function __construct(private ResourceRegistry $registry)
    {
    }

    public function resolve(AdminResource $child): ResolvedParentRelation
    {
        $parentRelation = $child->parent();
        if (null === $parentRelation) {
            throw new \LogicException(\sprintf('Resource "%s" is not nested: parent() returns null.', $child->getSlug()));
        }

        $parentClass = $parentRelation->getParentClass();
        try {
            $parent = $this->registry->getByClass($parentClass);
        } catch (\Throwable $e) {
            throw new \LogicException(\sprintf('Nested resource "%s" names parent "%s" which is not registered in the panel.', $child->getSlug(), $parentClass), 0, $e);
        }

        $name = $parentRelation->getRelationship();
        $relation = null;
        foreach ($parent->relations() as $candidate) {
            if ($candidate->getName() === $name) {
                $relation = $candidate;
                break;
            }
        }
        if (null === $relation) {
            throw new \LogicException(\sprintf('Nested resource "%s" names parent relationship "%s", but "%s" declares no relation "%s".', $child->getSlug(), $name, $parent->getSlug(), $name));
        }

        if (RelationKind::OneToMany !== $relation->getKind()) {
            throw new \LogicException(\sprintf('Nested resource "%s" must hang off a one-to-many parent relation; "%s.%s" is not one-to-many.', $child->getSlug(), $parent->getSlug(), $name));
        }

        $foreignKey = $parentRelation->getForeignKey();
        if ($relation->getForeignKey() !== $foreignKey) {
            throw new \LogicException(\sprintf('Nested resource "%s" foreignKey "%s" disagrees with parent relation "%s.%s" foreignKey "%s".', $child->getSlug(), $foreignKey, $parent->getSlug(), $name, (string) $relation->getForeignKey()));
        }

        return new ResolvedParentRelation(
            parentResource: $parent,
            relation: $relation,
            foreignKey: $foreignKey,
            recordTitleAttribute: $parentRelation->getRecordTitle() ?? $parent->getIdentifierField(),
        );
    }
}
