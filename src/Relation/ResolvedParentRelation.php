<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Resource\AdminResource;

/**
 * @internal immutable, validated view of a child resource's {@see ParentRelation}:
 * the resolved parent resource, the parent {@see Relation} that holds these
 * children, the child→parent foreign-key column, and the attribute used to title
 * the parent record in the breadcrumb
 */
final readonly class ResolvedParentRelation
{
    public function __construct(
        public AdminResource $parentResource,
        public Relation $relation,
        public string $foreignKey,
        public string $recordTitleAttribute,
    ) {
    }
}
