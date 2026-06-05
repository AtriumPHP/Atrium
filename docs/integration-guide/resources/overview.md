# Resources

> A resource is the single PHP class that describes how an entity appears in the
> panel — its table, its form, and the hooks that integrate your domain.

## When to use

Every entity you want to manage gets one `AdminResource` subclass. Small resources
inline their `table()` and `form()`; larger ones delegate to dedicated
`Tables/`, `Schemas/` and `Pages/` classes. Beyond layout, a resource exposes
**hooks** — override points where you plug in authorization, data shaping, side
effects, scoping and navigation without writing controllers.

## Example

```php
use Atrium\Resource\AdminResource;
use Atrium\Table\TableConfiguration;
use Atrium\Form\Schema;
use App\Entity\Article;

final class ArticleResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Article::class;
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([/* … */]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([/* … */]);
    }
}
```

## Record identity

Every record is addressed in URLs by a single field — the `{id}` segment of its
view, edit and action URLs. By default that field is `id`:

| Method | Purpose |
| --- | --- |
| `getIdentifierField(): string` | Name of the property that identifies a record in URLs. Defaults to `id`. |

The default covers the common cases without any configuration:

- an **auto-increment** integer key named `$id`;
- a **UUID** or **ULID** key named `$id` — the value is resolved through the
  key's Doctrine type, so `/{prefix}/{slug}/0b5f…` just works.

Override it when the record is addressed by a **differently-named** property —
either a primary key called something other than `id`, or a **natural key** such
as a slug used for human-readable URLs:

```php
final class ArticleResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Article::class;
    }

    // Address articles by their slug: /admin/articles/my-first-post
    public function getIdentifierField(): string
    {
        return 'slug';
    }
}
```

The chosen field is used both to **read** the identifier out of a record (to
build its row, view and edit links) and to **look the record back up** from a
URL, so its values must be **unique**. For a non-primary-key field, give the
column a unique index — the Doctrine adapter resolves the record with a
`WHERE <field> = :id` query, while a plain `id` key still takes the fast
identity-map path. [Query scoping](../data/query-scoping.md) applies to the
lookup either way: an id outside the resource's scope resolves to a 404.

## Lifecycle & hook reference

Every hook below is an override point on `AdminResource` with a safe default
(open, no-op, or identity). They are documented in detail on these pages:

### [Authorization](authorization.md) — who may do what

| Hook | Purpose |
| --- | --- |
| `canViewAny()` / `canCreate()` / `canView($r)` / `canEdit($r)` / `canDelete($r)` | Gate the list, create, and per-record view/edit/delete. |
| `can($ability, $record = null)` | Dispatch a named ability (used to gate `Action`s). |

### [Relations](relations.md) — manage related records on the parent

| Hook | Purpose |
| --- | --- |
| `relations()` | Declare one-to-many relation managers (`Relation::make(...)->oneToMany(...)`). |
| `canAssociate($parent, $child)` / `canDissociate($parent, $child)` | Gate link/unlink of related records. |

### [Record lifecycle hooks](lifecycle-hooks.md) — shape data & react to writes

| Hook | When |
| --- | --- |
| `mutateFormDataBeforeFill($data, $op)` | Before the form is populated (create defaults / edit record). |
| `mutateFormDataBeforeValidate($data, $op)` | Clean raw input before validation. |
| `afterValidate($data, $op)` | After a valid submit (side-effect only). |
| `mutateFormDataBeforeSave($data, $op)` | Transform data before it is written to the entity. |
| `beforeSave($r, $op)` / `afterSave($r, $op)` | Around persistence (in the transaction). |
| `handleRecordCreation($r, $writer)` / `handleRecordUpdate($r, $writer)` | Own the actual write (custom persistence). |
| `beforeDelete($r)` / `afterDelete($r)` | Around a delete. |
| `beforeAction($name, $r)` / `afterAction($name, $r)` | Around any row action. |
| `beforeBulkAction($name, $records)` / `afterBulkAction($name, $records)` | Around a bulk action. |

### [Query scoping](../data/query-scoping.md) — which records exist

| Hook | Purpose |
| --- | --- |
| `scopeQuery(DataQuery): DataQuery` | Narrow the records the resource exposes (tenant, owner, soft-delete). |

### [Navigation & access](navigation.md) — reachability & the menu

| Hook | Purpose |
| --- | --- |
| `canAccess()` | Resource-level gate: hides the nav entry and 403s every page. |
| `shouldRegisterNavigation()` | Reachable but hidden from the menu. |
| `getNavigationSort()` / `getNavigationBadge()` / `getNavigationBadgeColor()` / `getNavigationGroup()` / `getNavigationIcon()` | Menu order, badge, grouping, icon. |

## See also

- [Tables](../tables/) · [Forms](../forms/) · [Actions](../actions/) ·
  [Pages](../pages/) · [Data](../data/)
