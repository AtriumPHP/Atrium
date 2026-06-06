# Nesting resources

> Scope a resource under a parent record — `/admin/projects/42/tasks/7` — so its
> list, create, edit and view screens are filtered to that parent, with a
> breadcrumb trail back up the chain.

## When to use

Make a resource **nested** when its records only make sense in the context of a
parent and you want full-page CRUD scoped to that parent — a Project's Tasks, a
Course's Lessons, an Invoice's Line items. The child still has its own
[`AdminResource`](overview.md) (table, form, view, authorization); declaring a
**parent** adds a scoped route family under the parent record and turns the
parent's [relation manager](relations.md) into links that navigate into the
child's nested pages instead of inline modals.

Reach for a plain [relation manager](relations.md) (no `parent()`) when managing
the children inline on the parent's screen is enough; reach for nesting when the
child deserves its own full pages (its own table filters, view screen, deeper
relations) addressed by URL.

## Example

A `Task` belongs to a `Project` through the `Project` resource's `tasks`
relation and the `projectId` foreign key:

```php
use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;

final class ProjectResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Project::class;
    }

    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            Relation::make('tasks')->oneToMany(TaskResource::class)->foreignKey('projectId'),
        ];
    }
}
```

```php
use Atrium\Relation\ParentRelation;
use Atrium\Resource\AdminResource;

final class TaskResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Task::class;
    }

    // Declaring a parent makes Task a nested resource.
    public function parent(): ?ParentRelation
    {
        return ParentRelation::make(ProjectResource::class)
            ->relationship('tasks')   // the parent relation (above) that holds these children
            ->foreignKey('projectId') // child → parent FK; must equal that relation's foreignKey
            ->recordTitle('name');    // how to title the parent record in the breadcrumb
    }
}
```

With this in place:

- The nested URL family is served: `/admin/project/{projectId}/task` (list),
  `/admin/project/{projectId}/task/new` (create), `/admin/project/{projectId}/task/{id}`
  (view) and `/admin/project/{projectId}/task/{id}/edit` (edit). Slugs come from the
  entity short name as usual (`Project` → `project`, `Task` → `task`).
- Every nested screen is **scoped to the parent**: the list shows only that
  project's tasks, create **presets the foreign key** so the new task is linked in
  one save, and a task belonging to *another* project (a forged id) is a **404** —
  the child is resolved against both its own `scopeQuery()` and the parent FK.
- A **breadcrumb** renders the trail: `Projects › Alpha › Tasks`, the current
  record being the page heading.
- On the **Project**'s own pages, the `tasks` relation manager detects that its
  target is nested here and switches from inline modals to **links**: each row
  navigates to the task's nested view (or edit), and **New** links to the nested
  create page.

Validation is **lazy** (on first use): if the named parent isn't registered, the
relationship doesn't exist on it, it isn't one-to-many, or the foreign keys
disagree, a clear `LogicException` is thrown when the nested screen is first
reached.

## Single parent segment

The nested route family carries **one** parent segment
(`/{parentResource}/{parentId}/{resource}/…`). Deeper ancestry is reached by
**navigating** — from a task's page, that task's own relation managers link one
level further down, re-rooting the parent segment — rather than stacking many
segments in a single URL. `PageContext.parentRecords` is a list for
forward-compatibility but holds the single resolved parent.

## API reference

### `AdminResource`

| Method | Description |
| --- | --- |
| `parent(): ?ParentRelation` | Declare the resource is nested under a parent record. Returns `null` (top-level) by default; override to return a `ParentRelation`. |

### `ParentRelation`

| Method | Description |
| --- | --- |
| `ParentRelation::make(string $parentResourceClass): self` | Start a declaration naming the parent **resource** class. |
| `relationship(string $name): self` | The name of the parent's `relations()` entry (a one-to-many `Relation`) that holds these children. |
| `foreignKey(string $column): self` | The child column holding the parent id. Must equal that relation's `foreignKey()` (boot-checked lazily). |
| `recordTitle(string $attribute): self` | Optional. The parent attribute used to title the parent record in the breadcrumb. Defaults to the parent's identifier field. |

### `PageContext` (nested additions)

| Member | Description |
| --- | --- |
| `parentResourceSlug: ?string` | The parent resource slug when the page is nested, else `null`. |
| `parentRecordId: ?string` | The parent record id when nested, else `null`. |
| `parentRecords: list<object>` | Resolved ancestry, nearest last (the single resolved parent). |
| `nestedUrl(string $action, ?string $id = null): string` | Build a nested URL (`index`/`create`/`edit`/`view`); falls back to the flat URL when not nested. |

## See also

- [Relations](relations.md) — inline relation managers on the parent's screens.
- [Query scoping](../data/query-scoping.md) — how `scopeQuery()` narrows records;
  the parent FK scope composes on top of it.
