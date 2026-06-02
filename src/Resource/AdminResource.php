<?php

declare(strict_types=1);

namespace Atrium\Resource;

use Atrium\Form\Schema;
use Atrium\Layout\Component;
use Atrium\Layout\Wizard;
use Atrium\Page\CreatePage;
use Atrium\Page\EditPage;
use Atrium\Page\ListPage;
use Atrium\Page\Page;
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
     * Configure the create/edit form schema (FRM-01).
     *
     * Inline for small resources, or delegated to a dedicated `Schemas/*` class
     * (PRD §9b). Returns the schema unchanged by default.
     */
    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * The Live Component that renders this resource's form. Defaults to the
     * plain `Atrium:Form`, upgrading to `Atrium:WizardForm` when the schema
     * contains a {@see Wizard}. Override to use a fully custom form component.
     */
    public function getFormComponentName(): string
    {
        return $this->schemaContainsWizard($this->form(new Schema())->getComponents())
            ? 'Atrium:WizardForm'
            : 'Atrium:Form';
    }

    /**
     * @param list<Component> $components
     */
    private function schemaContainsWizard(array $components): bool
    {
        foreach ($components as $component) {
            if ($component instanceof Wizard) {
                return true;
            }
            if ($this->schemaContainsWizard($component->getChildComponents())) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map action keys (index/create/edit/custom) to Page classes (RES-06).
     * Override to supply custom Page subclasses per §9b.
     *
     * @return array<string, class-string<Page>>
     */
    public static function pages(): array
    {
        return [
            'index' => ListPage::class,
            'create' => CreatePage::class,
            'edit' => EditPage::class,
        ];
    }

    /**
     * Instantiate the Page for an action, or null if the resource exposes none.
     */
    public function resolvePage(string $action): ?Page
    {
        $class = static::pages()[$action] ?? null;

        return null === $class ? null : new $class();
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
     * Human-readable singular label (e.g. "Customer").
     */
    public function getSingularLabel(): string
    {
        return ucfirst(str_replace('-', ' ', $this->getSlug()));
    }

    /**
     * Human-readable, pluralised label for navigation and headings.
     */
    public function getLabel(): string
    {
        return $this->getSingularLabel().'s';
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
