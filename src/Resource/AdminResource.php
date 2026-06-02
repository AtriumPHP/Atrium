<?php

declare(strict_types=1);

namespace Atrium\Resource;

use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Form\Schema;
use Atrium\Layout\Component;
use Atrium\Layout\Wizard;
use Atrium\Page\CreatePage;
use Atrium\Page\EditPage;
use Atrium\Page\ListPage;
use Atrium\Page\Page;
use Atrium\Table\TableConfiguration;

/**
 * Base class for every admin resource.
 *
 * A resource is a thin, PHP-only description of how an entity is presented in
 * the panel. Small resources may inline their configuration (see {@see table()}
 * and {@see form()}); non-trivial ones delegate to dedicated Tables/, Schemas/
 * and Pages/ classes
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
     * Configure the list table — columns plus record, header and bulk actions
     * (inline configuration for small resources; delegate to a dedicated
     * `Tables/*` class for larger ones).
     *
     * The given {@see TableConfiguration} already carries the framework defaults
     * (an Edit record action and a "New" header action); set columns and any
     * extra actions on it and return it. Returns it unchanged by default.
     */
    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table;
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

    // -- Authorization hooks ---------------------------------------------------
    //
    // Override these to integrate the host app's security (e.g. Symfony Voters /
    // isGranted). They are enforced server-side at the page boundary, on form
    // save, and on action execution — and additionally hide the built-in actions
    // a user may not perform. The default is open (everything allowed): the core
    // stays security-agnostic and the app opts in.

    public function canViewAny(): bool
    {
        return true;
    }

    public function canCreate(): bool
    {
        return true;
    }

    public function canEdit(object $record): bool
    {
        return true;
    }

    public function canDelete(object $record): bool
    {
        return true;
    }

    public function canView(object $record): bool
    {
        return true;
    }

    /**
     * Dispatch a named ability check to the matching hook above. Used to gate an
     * {@see \Atrium\Action\Action} by its {@see \Atrium\Action\Action::getAbility()}.
     * Record-scoped abilities are denied when no record is supplied; an unknown
     * ability is allowed (it is not an Atrium-managed permission).
     */
    public function can(string $ability, ?object $record = null): bool
    {
        return match ($ability) {
            'viewAny' => $this->canViewAny(),
            'create' => $this->canCreate(),
            'view' => null !== $record && $this->canView($record),
            'edit' => null !== $record && $this->canEdit($record),
            'delete' => null !== $record && $this->canDelete($record),
            default => true,
        };
    }

    // -- Query scoping ---------------------------------------------------------
    //
    // Override scopeQuery() to constrain *which* records this resource exposes —
    // multi-tenancy, ownership, soft-deletes. Unlike the authorization hooks
    // (which hide actions on a row the user can still see), scoping removes the
    // rows entirely: it is applied to the list, the count, select-all, and to
    // record resolution (edit/actions), so an out-of-scope id resolves to null.

    /**
     * Narrow the records this resource exposes by returning a query with extra
     * equality conditions. Use {@see DataQuery::withFilters()} to add them, e.g.
     * `return $query->withFilters(['tenantId' => $this->tenant, 'deletedAt' => null]);`.
     *
     * Scope is expressed as `field => value` equality (with `null` meaning IS
     * NULL) — enough for tenant/owner/soft-delete. Returns the query unchanged by
     * default (no scoping). Field names must be trusted developer configuration;
     * values are bound as parameters by the data provider.
     */
    public function scopeQuery(DataQuery $query): DataQuery
    {
        return $query;
    }

    /**
     * The scope's equality conditions alone, derived from {@see scopeQuery()} —
     * used to scope single-record resolution (`find`) so the same boundary that
     * filters the list also hides out-of-scope ids. Not an extension point.
     *
     * @return array<string, scalar|bool|null>
     */
    final public function scopeFilters(): array
    {
        return $this->scopeQuery(new DataQuery())->filters;
    }

    // -- Record lifecycle hooks ------------------------------------------------
    //
    // Override these to shape data and run side effects around persistence. The
    // mutate hooks transform the form-state array (field name => value); the
    // before/after hooks receive the entity itself, for setting non-field
    // properties (ownership, timestamps, relations) and side effects.

    /**
     * Transform the data that fills the form before it is shown. Runs for both
     * operations: on `edit` it receives the record's data; on `create` it
     * receives the fields' defaults, so it can seed create-form values (a default
     * owner, today's date). Receives and returns a `field name => value` map.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeFill(array $data, string $operation): array
    {
        return $data;
    }

    /**
     * Transform the raw submitted data before it is validated. Use it to coerce
     * input the user shouldn't have to get exactly right (trim, upper-case a
     * code, drop empties) so validation sees the cleaned value. `$operation` is
     * `create` or `edit`. Runs before {@see mutateFormDataBeforeSave()}.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeValidate(array $data, string $operation): array
    {
        return $data;
    }

    /**
     * React to the data once it has passed validation, before persistence — e.g.
     * a cross-field check that raises no field error, logging, or deriving a
     * read-only warning. `$data` is the validated, normalised field map; this is
     * a side-effect hook (it does not transform the data — use
     * {@see mutateFormDataBeforeSave()} for that). Runs only on a valid submit.
     *
     * @param array<string, mixed> $data
     */
    public function afterValidate(array $data, string $operation): void
    {
    }

    /**
     * Transform the submitted form data before it is written to the entity.
     * `$operation` is `create` or `edit`.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeSave(array $data, string $operation): array
    {
        return $data;
    }

    /**
     * Run just before the entity is persisted (after form fields are applied).
     * Set non-field properties here (e.g. `$record->owner = ...`). `$operation`
     * is `create` or `edit`.
     */
    public function beforeSave(object $record, string $operation): void
    {
    }

    /**
     * Run just after the entity is persisted. `$operation` is `create` or `edit`.
     */
    public function afterSave(object $record, string $operation): void
    {
    }

    /**
     * Persist a newly-created record. The default writes through the data writer;
     * override to persist through a service, a command bus or an API instead —
     * the form's create path calls this rather than the writer directly. Runs
     * inside the save transaction, between {@see beforeSave()} and
     * {@see afterSave()}.
     */
    public function handleRecordCreation(object $record, DataWriterInterface $writer): void
    {
        $writer->create($record);
    }

    /**
     * Persist an updated record. The default writes through the data writer;
     * override to route the update through your own persistence. Runs inside the
     * save transaction, between {@see beforeSave()} and {@see afterSave()}.
     */
    public function handleRecordUpdate(object $record, DataWriterInterface $writer): void
    {
        $writer->update($record);
    }

    /**
     * Run just before a record is deleted (via the built-in delete actions).
     */
    public function beforeDelete(object $record): void
    {
    }

    /**
     * Run just after a record is deleted.
     */
    public function afterDelete(object $record): void
    {
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
