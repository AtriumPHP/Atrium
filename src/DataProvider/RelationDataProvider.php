<?php

declare(strict_types=1);

namespace Atrium\DataProvider;

use Atrium\Relation\RelationDescriptor;

/**
 * Backend-agnostic relationship read/link operations (REL-10). The descriptor
 * names the keys/pivot; the adapter executes them. Core passes the descriptor +
 * parent + child and never sees a storage type. List queries receive a
 * {@see DataQuery} already carrying the target resource's scope, search, sort and
 * pagination; the adapter composes the parent (foreign-key or pivot) scope on top.
 */
interface RelationDataProvider
{
    /** @return iterable<object> */
    public function listRelated(RelationDescriptor $relation, object $parent, DataQuery $query): iterable;

    public function countRelated(RelationDescriptor $relation, object $parent, DataQuery $query): int;

    /**
     * Candidate records for an Associate/Attach picker: records NOT already linked.
     *
     * @return iterable<object>
     */
    public function listLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): iterable;

    public function countLinkable(RelationDescriptor $relation, object $parent, DataQuery $query): int;

    public function associate(RelationDescriptor $relation, object $parent, object $child): void;

    public function dissociate(RelationDescriptor $relation, object $parent, object $child): void;

    /** @param array<string, scalar|null> $pivot */
    public function attach(RelationDescriptor $relation, object $parent, object $child, array $pivot = []): void;

    public function detach(RelationDescriptor $relation, object $parent, object $child): void;
}
