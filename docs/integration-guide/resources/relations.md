# Relations

> Show and manage a record's related records — its comments, line items, tags —
> on the parent's Edit and View screens, with their own table, create/edit and
> link/unlink actions, scoped to the parent.

## When to use

Declare a relation whenever one resource owns or references rows of another and
you want to manage them in the parent's context rather than only as a separate
top-level resource — a Post's Comments, an Order's Items, an Author's Books.
A relation manager renders a parent-scoped table beneath the parent's form (and,
read-only, on its View screen) with the child resource's columns and a full
lifecycle: create/edit (in a modal), delete, and attach/detach existing records.

This page covers **one-to-many** relations (a child points back at one parent
via a foreign key) and **many-to-many** relations (linked through a pivot table).
When a relation's target is itself a **nested resource** (it declares a
[`parent()`](nesting.md)), its manager links each row into the child's full
nested pages and points **New** at the nested create page instead of opening an
inline modal — see [Nesting resources](nesting.md).

## Example

Declare relations on the parent resource with `relations()`. A relation names the
**target resource** (not the entity) and the **foreign key** on the child:

```php
use Atrium\Resource\AdminResource;
use Atrium\Relation\Relation;
use App\Entity\Post;

final class PostResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Post::class;
    }

    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            Relation::make('comments')
                ->oneToMany(CommentResource::class)   // the target *resource*
                ->foreignKey('postId')                // Comment.postId → Post.id
                ->recordTitle('body')                 // labels the attach picker
                ->emptyState('No comments yet', 'Add the first one below.'),
        ];
    }
}
```

The `comments` manager now appears on the Post Edit page: a table of that post's
comments (using `CommentResource`'s `table()` columns) with **New comment**
(opens a modal hosting `CommentResource`'s `form()`, with `postId` pre-set), an
**Attach existing** picker (comments not yet linked to any post), a row click to
**edit**, a **Detach** row action (clears the FK), and **Delete** / bulk delete.

On the Post **View** page the same manager renders **read-only** by default
(list + pagination only); opt a relation into editable-on-view with
`->readOnlyOnView(false)`.

### Authorization

Owned actions (create/edit/delete) gate on the **target** resource's existing
hooks (`CommentResource::canCreate()` / `canEdit($comment)` / `canDelete($comment)`).
Link/unlink gate on the **parent** resource via two hooks that receive both
records:

```php
final class PostResource extends AdminResource
{
    // …

    public function canDissociate(object $parent, object $child): bool
    {
        return $this->security->isGranted('EDIT', $parent);
    }
}
```

Both default to allow. Like every Atrium action, a denied affordance is **hidden
and refuses to run** if triggered directly. The related list also always passes
through the target resource's `scopeQuery()`, so a relation never surfaces a row
the resource itself would hide, and an owned record of another parent 404s.

## Many-to-many relations

Declare a many-to-many relation with `manyToMany()` plus the **pivot table** and
its two key columns. A pivot relation links *existing* records, so the manager
offers **Attach** / **Detach** (not create/edit/delete of the child):

```php
public function relations(): array
{
    return [
        Relation::make('tags')
            ->manyToMany(TagResource::class)
            ->pivotTable('post_tag')          // the join table
            ->pivotKeys('post_id', 'tag_id')  // parent key, related key
            ->pivotColumns(['note'])          // extra columns captured on attach
            ->recordTitle('name'),
    ];
}
```

- **Attach existing** opens a picker over the records not yet linked (`listLinkable`
  excludes rows already in the pivot for this parent), plus an input per
  `pivotColumns()` entry — those values are written onto the new pivot row.
- **Detach** (row + bulk) removes the pivot row; **both records persist**.
- Authorization uses the parent resource's **`canAttach`/`canDetach($parent, $child)`**
  hooks (default-allow), hidden-and-refused like every action.
- The pivot is **never mapped as a Doctrine entity** — the Doctrine adapter reads
  and writes it through DBAL; column/table names come from your `Relation` config,
  the values are bound as parameters.

> **Note.** REL-M3 captures and stores pivot-column values on Attach; *displaying*
> them as extra columns in the related table is a later enhancement.

## Several relations: tabs

When a resource declares more than one relation, the host renders a **server-driven
tab strip** and mounts only the active relation's manager at a time (one tab's
table is loaded per request). A single relation renders as one titled section. No
configuration is needed — declare the relations and the tabs appear.

## API reference

### `Relation` (declared in `relations()`)

| Method | Description |
| --- | --- |
| `Relation::make(string $name)` | Start a relation; `$name` is its key and default label. |
| `->oneToMany(string $resource)` | A one-to-many target **resource** class-string (resolved via the registry). |
| `->manyToMany(string $resource)` | A many-to-many target resource (linked through a pivot table). |
| `->foreignKey(string $field)` | One-to-many: the child property holding the parent id. |
| `->pivotTable(string $table)` | Many-to-many: the join table name. |
| `->pivotKeys(string $parent, string $related)` | Many-to-many: the pivot's parent-key and related-key columns. |
| `->pivotColumns(array $columns)` | Many-to-many: extra pivot columns captured on Attach. |
| `->recordTitle(string $attribute)` | Child attribute shown in the Attach/Associate picker (defaults to the id). |
| `->label(string)` / `->icon(?string)` | Section/tab heading + icon. |
| `->emptyState(string $heading, ?string $description = null, ?string $icon = null)` | Empty-table message. |
| `->readOnlyOnView(bool = true)` | Whether the manager is read-only on the View screen (default `true`). |
| `->visible(bool\|\Closure $condition)` | Show the relation only when the predicate holds for the parent record. |
| `->table(\Closure)` / `->form(\Closure)` | Override the target's `table()` / `form()` for this relation. |

### `AdminResource` — relation authorization (public API)

| Hook | Default | When |
| --- | --- | --- |
| `canAssociate(object $parent, object $child): bool` | `true` | Before linking a child (one-to-many Associate). |
| `canDissociate(object $parent, object $child): bool` | `true` | Before unlinking a child (one-to-many Dissociate). |
| `canAttach(object $parent, object $child): bool` | `true` | Before linking a child (many-to-many Attach). |
| `canDetach(object $parent, object $child): bool` | `true` | Before unlinking a child (many-to-many Detach). |

Owned create/edit/delete (one-to-many) continue to use the target resource's
`canCreate()` / `canEdit($r)` / `canDelete($r)`.

## Notes

- **Create links in one transaction.** The modal create writes the child and sets
  the parent foreign key in a single `DataWriterInterface::transactional()` call,
  so a failure leaves nothing half-saved.
- **The Attach picker** lists only candidates not already linked (one-to-many: the
  foreign key is null), within the target's scope, and re-validates the choice on
  submit. It currently offers up to 100 candidates — a searchable picker for very
  large sets is a future enhancement.
- **Owned row View** (navigating to a child's own View/Edit page) lands with
  nested resources; in one-to-many a row click opens the inline Edit modal.
- **No Doctrine in core.** All relation reads/writes go through the
  `RelationDataProvider` seam (Doctrine + array adapters); the descriptor names the
  keys, never user input.
