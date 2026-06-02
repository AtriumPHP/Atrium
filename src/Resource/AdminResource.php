<?php

declare(strict_types=1);

namespace Atrium\Resource;

use Atrium\Table\Column;

/**
 * Base class for every admin resource.
 *
 * A resource is a thin, PHP-only description of how an entity is presented in
 * the panel. Small resources may inline their configuration (see {@see columns()});
 * non-trivial ones delegate to dedicated Tables/, Schemas/ and Pages/ classes
 * (added in later phases). This signature is part of the public API contract
 * (PRD §9) — treat changes as BC-relevant.
 */
abstract class AdminResource
{
    /**
     * Fully-qualified class name of the entity this resource manages.
     *
     * @return class-string
     */
    abstract public function getEntityClass(): string;

    /**
     * List columns for the table view (inline configuration for small resources).
     *
     * @return list<Column>
     */
    public function columns(): array
    {
        return [];
    }

    /**
     * URL-friendly identifier, used for routing and registry lookups.
     *
     * Defaults to a kebab-case form of the entity's short name.
     */
    public function getSlug(): string
    {
        $short = (new \ReflectionClass($this->getEntityClass()))->getShortName();
        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $short) ?? $short;

        return strtolower($kebab);
    }

    /**
     * Human-readable, pluralised label for navigation and headings.
     */
    public function getLabel(): string
    {
        return ucfirst(str_replace('-', ' ', $this->getSlug())).'s';
    }

    /**
     * Optional navigation icon identifier (resolved by the theme layer).
     */
    public function getNavigationIcon(): ?string
    {
        return null;
    }

    /**
     * Optional navigation group this resource is listed under.
     */
    public function getNavigationGroup(): ?string
    {
        return null;
    }
}
