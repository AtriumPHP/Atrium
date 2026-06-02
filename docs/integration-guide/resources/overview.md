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

## Lifecycle & hook reference

Every hook below is an override point on `AdminResource` with a safe default
(open, no-op, or identity). They are documented in detail on these pages:

### [Authorization](authorization.md) — who may do what

| Hook | Purpose |
| --- | --- |
| `canViewAny()` / `canCreate()` / `canView($r)` / `canEdit($r)` / `canDelete($r)` | Gate the list, create, and per-record view/edit/delete. |
| `can($ability, $record = null)` | Dispatch a named ability (used to gate `Action`s). |

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
