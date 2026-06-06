<?php

declare(strict_types=1);

namespace Atrium\Resource;

use Atrium\Action\Action;
use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Form\Field\Field;
use Atrium\Form\Schema;
use Atrium\Layout\Component;
use Atrium\Layout\Wizard;
use Atrium\Page\CreatePage;
use Atrium\Page\EditPage;
use Atrium\Page\ListPage;
use Atrium\Page\ListWidgetsConfiguration;
use Atrium\Page\Page;
use Atrium\Page\PageContext;
use Atrium\Page\ViewPage;
use Atrium\Table\TableConfiguration;
use Atrium\View\TextEntry;

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
     * Name of the entity property that identifies a record in URLs.
     *
     * Defaults to `id`, which covers an auto-increment key and a UUID/ULID key
     * alike, as long as the property is named `$id` (the value is resolved
     * through the key's type when the record is looked up). Override this when
     * the record is addressed by a differently-named property — a primary key
     * called `$uuid`, or a natural key such as a `$slug` used for pretty URLs:
     *
     *     public function getIdentifierField(): string
     *     {
     *         return 'slug';
     *     }
     *
     * The chosen field is used both to read the identifier out of a record (to
     * build its row/view/edit URLs) and to look a record back up from a URL, so
     * its values must be unique. For a non-primary-key field, the backing store
     * must be able to resolve a record by it (the Doctrine adapter queries by
     * the field; a unique index is recommended).
     */
    public function getIdentifierField(): string
    {
        return 'id';
    }

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

    /**
     * Configure the read-only record View screen (VIEW-02): a {@see Schema} of
     * {@see \Atrium\View\Entry} components (with the same layout containers as a
     * form). Empty by default — a resource with a `'view'` page but no `view()`
     * falls back to its {@see form()} rendered read-only (see
     * {@see resolveViewSchema()}).
     */
    public function view(Schema $schema): Schema
    {
        return $schema;
    }

    /**
     * Declare this resource's managed relationships (REL-01). Each
     * {@see \Atrium\Relation\Relation} renders as a relation manager on the
     * resource's Edit/View screens. Returns none by default.
     *
     * @return list<\Atrium\Relation\Relation>
     */
    public function relations(): array
    {
        return [];
    }

    /**
     * Declare this resource is nested under a parent record (REL-14): return a
     * {@see \Atrium\Relation\ParentRelation} naming the parent resource, the parent
     * relation that holds these children, and the child→parent foreign key. The
     * default `null` means the resource is top-level (not nested).
     */
    public function parent(): ?\Atrium\Relation\ParentRelation
    {
        return null;
    }

    /**
     * The schema the View screen renders: the resource's {@see view()} when it
     * declares one, otherwise the {@see form()} fields mapped to read-only text
     * entries (the free fallback — a flat list; declare `view()` for layout).
     *
     * @internal
     */
    final public function resolveViewSchema(): Schema
    {
        $view = $this->view(new Schema());
        if ([] !== $view->getComponents()) {
            return $view;
        }

        $entries = array_map(
            static fn (Field $field): TextEntry => TextEntry::make($field->getName())->label($field->getLabel()),
            $this->form(new Schema())->getFields(),
        );

        return (new Schema())->components($entries);
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
     * Whether $child may be linked to $parent through a one-to-many relation
     * (set its foreign key). Default-allow; override to restrict. Re-checked at
     * execution by the relation manager. Public API (REL-12).
     */
    public function canAssociate(object $parent, object $child): bool
    {
        return true;
    }

    /**
     * Whether $child may be unlinked from $parent (clear its foreign key; the
     * record persists). Default-allow; override to restrict. Public API (REL-12).
     */
    public function canDissociate(object $parent, object $child): bool
    {
        return true;
    }

    /**
     * Whether $child may be attached to $parent through a many-to-many relation
     * (insert a pivot row). Default-allow; override to restrict. Public API (REL-12).
     */
    public function canAttach(object $parent, object $child): bool
    {
        return true;
    }

    /**
     * Whether $child may be detached from $parent (remove the pivot row; both
     * records persist). Default-allow; override to restrict. Public API (REL-12).
     */
    public function canDetach(object $parent, object $child): bool
    {
        return true;
    }

    /**
     * Dispatch a named ability check to the matching hook above. Used to gate an
     * {@see Action} by its {@see Action::getAbility()}.
     * Record-scoped abilities are denied when no record is supplied; an unknown
     * ability is allowed (it is not an Atrium-managed permission). Relation
     * link/unlink use {@see canAssociate()}/{@see canDissociate()} (one-to-many)
     * and {@see canAttach()}/{@see canDetach()} (many-to-many), which take both
     * the parent and child, so they are checked directly by the relation manager
     * rather than through this single-subject dispatch.
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

    // -- Action lifecycle hooks ------------------------------------------------
    //
    // Generic seams around *any* table action run (the per-record `before/after
    // Delete` above are the delete-specific case). Use them for cross-cutting
    // concerns — audit logging, cache busting, metrics — keyed by action name.
    // They fire inside the same transaction as the action's handler.

    /**
     * Run before a record (row) action's handler. `$action` is the action's
     * name; `$record` is the row it targets.
     */
    public function beforeAction(string $action, object $record): void
    {
    }

    /**
     * Run after a record (row) action's handler.
     */
    public function afterAction(string $action, object $record): void
    {
    }

    /**
     * Run before a bulk action's handler, with every record it will act on
     * (already filtered to those the user is allowed to act on).
     *
     * @param list<object> $records
     */
    public function beforeBulkAction(string $action, array $records): void
    {
    }

    /**
     * Run after a bulk action's handler.
     *
     * @param list<object> $records
     */
    public function afterBulkAction(string $action, array $records): void
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

    // -- Per-screen presentation (resolution hub) ------------------------------
    //
    // Per-screen presentation — header actions and the list's header/footer widget
    // bands — lives on the Page classes ({@see Page::getHeaderActions()},
    // {@see ListPage::headerWidgets()}), next to the heading/subheading. The
    // resource owns pages() and so resolves which Page handles an action and asks
    // it; the host Live Components and the controller call these @internal
    // resolvers rather than the pages directly.

    /**
     * The header actions for a screen, from the Page that handles it
     * ({@see ListPage} adds the "New" button; a custom page adds its own). The
     * host Live Component renders and dispatches the result.
     *
     * @return list<Action>
     *
     * @internal
     */
    final public function resolveHeaderActions(string $action, PageContext $context): array
    {
        return $this->resolvePage($action)?->getHeaderActions($context) ?? [];
    }

    /**
     * The resolved header widgets for the list screen — the tree from
     * {@see ListPage::headerWidgets()} with the resource-identity context (slug,
     * path prefix, labels) baked onto every slot, so widgets can scope their own
     * data without extra wiring.
     *
     * @internal
     */
    final public function resolveHeaderWidgets(PageContext $context): ListWidgetsConfiguration
    {
        $page = $this->resolvePage('index');
        $config = $page instanceof ListPage ? $page->headerWidgets(new ListWidgetsConfiguration()) : new ListWidgetsConfiguration();

        return $config->applyContext($this->widgetContext($context));
    }

    /**
     * The resolved footer widgets for the list screen (see
     * {@see resolveHeaderWidgets()}).
     *
     * @internal
     */
    final public function resolveFooterWidgets(PageContext $context): ListWidgetsConfiguration
    {
        $page = $this->resolvePage('index');
        $config = $page instanceof ListPage ? $page->footerWidgets(new ListWidgetsConfiguration()) : new ListWidgetsConfiguration();

        return $config->applyContext($this->widgetContext($context));
    }

    /**
     * The resolved header widgets for the **View** screen (VIEW-16) — the band from
     * {@see ViewPage::headerWidgets()} with the resource identity **and the record
     * id** baked onto every slot, so a record-scoped widget (related rows, an
     * activity timeline) can fetch its own data.
     *
     * @internal
     */
    final public function resolveViewHeaderWidgets(PageContext $context): ListWidgetsConfiguration
    {
        $page = $this->resolvePage('view');
        $config = $page instanceof ViewPage ? $page->headerWidgets(new ListWidgetsConfiguration()) : new ListWidgetsConfiguration();

        return $config->applyContext($this->widgetContext($context));
    }

    /**
     * The resolved footer widgets for the View screen (see
     * {@see resolveViewHeaderWidgets()}).
     *
     * @internal
     */
    final public function resolveViewFooterWidgets(PageContext $context): ListWidgetsConfiguration
    {
        $page = $this->resolvePage('view');
        $config = $page instanceof ViewPage ? $page->footerWidgets(new ListWidgetsConfiguration()) : new ListWidgetsConfiguration();

        return $config->applyContext($this->widgetContext($context));
    }

    /**
     * Resource-identity context forwarded to every screen widget slot. `recordId`
     * is the record's id on the View screen, and null on the list (no record).
     *
     * @return array<string, mixed>
     */
    private function widgetContext(PageContext $context): array
    {
        return [
            'resource' => $context->resourceSlug,
            'pathPrefix' => $context->pathPrefix,
            'singularLabel' => $context->singularLabel,
            'pluralLabel' => $context->pluralLabel,
            'recordId' => $context->entityId,
        ];
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

    // -- Navigation & access ---------------------------------------------------

    /**
     * Whether this resource is reachable at all. It gates both the navigation
     * entry (a resource you cannot access is not shown) and every page (the
     * controller returns 403). Defaults to {@see canViewAny()}, so by default
     * "can see the list" and "can reach the resource" coincide; override to
     * separate them (e.g. a resource reached only through links). Page-specific
     * abilities ({@see canCreate()} / {@see canEdit()}) still apply on top.
     */
    public function canAccess(): bool
    {
        return $this->canViewAny();
    }

    /**
     * Whether to list this resource in the navigation. Return false to keep it
     * accessible (its pages work) but hidden from the menu — e.g. a detail
     * resource you only ever link to.
     */
    public function shouldRegisterNavigation(): bool
    {
        return true;
    }

    /**
     * Sort weight for the navigation entry — lower comes first. Entries without a
     * weight (null) sort after weighted ones, in registration order.
     */
    public function getNavigationSort(): ?int
    {
        return null;
    }

    /**
     * Optional badge shown next to the navigation entry (e.g. a pending count).
     * Return null for no badge.
     */
    public function getNavigationBadge(): ?string
    {
        return null;
    }

    /**
     * Semantic colour key for the navigation badge (gray, primary, red, green,
     * amber, sky). Defaults to `primary`; only consulted when
     * {@see getNavigationBadge()} returns a value.
     */
    public function getNavigationBadgeColor(): string
    {
        return 'primary';
    }
}
