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
via a foreign key). Many-to-many (pivot) relations and nested resources arrive in
later milestones.

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

## API reference

### `Relation` (declared in `relations()`)

| Method | Description |
| --- | --- |
| `Relation::make(string $name)` | Start a relation; `$name` is its key and default label. |
| `->oneToMany(string $resource)` | The target **resource** class-string (resolved via the registry). |
| `->foreignKey(string $field)` | The child property holding the parent id. |
| `->recordTitle(string $attribute)` | Child attribute shown in the Attach picker (defaults to the id). |
| `->label(string)` / `->icon(?string)` | Section heading + icon. |
| `->emptyState(string $heading, ?string $description = null, ?string $icon = null)` | Empty-table message. |
| `->readOnlyOnView(bool = true)` | Whether the manager is read-only on the View screen (default `true`). |
| `->visible(bool\|\Closure $condition)` | Show the relation only when the predicate holds for the parent record. |
| `->table(\Closure)` / `->form(\Closure)` | Override the target's `table()` / `form()` for this relation. |

### `AdminResource` — relation authorization (public API)

| Hook | Default | When |
| --- | --- | --- |
| `canAssociate(object $parent, object $child): bool` | `true` | Before linking an existing child (Attach). |
| `canDissociate(object $parent, object $child): bool` | `true` | Before unlinking a child (Detach). |

Owned create/edit/delete continue to use the target resource's
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
