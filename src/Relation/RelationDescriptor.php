<?php

declare(strict_types=1);

namespace Atrium\Relation;

/**
 * @internal immutable, fully-resolved view of a {@see Relation} consumed by the
 * data layer and the relation manager: entity classes and key names only, with
 * defaults filled in and the target resolved — no closures, no Doctrine types
 */
final readonly class RelationDescriptor
{
    /**
     * @param class-string $childEntityClass
     * @param list<string> $pivotColumns
     */
    public function __construct(
        public string $name,
        public RelationKind $kind,
        public string $childEntityClass,
        public string $childIdField,
        public string $parentIdField,
        public string $recordTitleAttribute,
        public ?string $foreignKey = null,
        public ?string $pivotTable = null,
        public ?string $pivotParentKey = null,
        public ?string $pivotRelatedKey = null,
        public array $pivotColumns = [],
    ) {
    }
}
