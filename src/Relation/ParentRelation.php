<?php

declare(strict_types=1);

namespace Atrium\Relation;

use Atrium\Resource\AdminResource;

/**
 * Declares that a resource is a *nested resource* (REL-14): its records live
 * under a parent record, reached at `/{prefix}/{parentResource}/{parentId}/{resource}/...`.
 * Configured in PHP on a child resource's {@see AdminResource::parent()}. The
 * parent class, the parent relation that holds these children, and the child→parent
 * foreign key are stated explicitly; a {@see ParentRelationResolver} validates them
 * against the registry on first use.
 */
final class ParentRelation
{
    private ?string $relationship = null;
    private ?string $foreignKey = null;
    private ?string $recordTitle = null;

    /** @param class-string<AdminResource> $parent */
    private function __construct(private readonly string $parent)
    {
    }

    /** @param class-string<AdminResource> $parent */
    public static function make(string $parent): self
    {
        return new self($parent);
    }

    /** The name of the parent's {@see Relation} (REL-01) that holds these children. */
    public function relationship(string $name): self
    {
        $this->relationship = $name;

        return $this;
    }

    /** The child column holding the parent's id (must equal that relation's foreignKey). */
    public function foreignKey(string $column): self
    {
        $this->foreignKey = $column;

        return $this;
    }

    /** Attribute used to title the parent record in the breadcrumb (default: the parent's identifier field). */
    public function recordTitle(string $attribute): self
    {
        $this->recordTitle = $attribute;

        return $this;
    }

    /** @return class-string<AdminResource> */
    public function getParentClass(): string
    {
        return $this->parent;
    }

    public function getRelationship(): string
    {
        return $this->relationship ?? throw new \LogicException(\sprintf('Nested resource parent of "%s" has no relationship(); call relationship().', $this->parent));
    }

    public function getForeignKey(): string
    {
        return $this->foreignKey ?? throw new \LogicException(\sprintf('Nested resource parent of "%s" has no foreignKey(); call foreignKey().', $this->parent));
    }

    public function getRecordTitle(): ?string
    {
        return $this->recordTitle;
    }
}
