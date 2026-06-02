# Authorization

> Gate who can list, create, edit, view and delete a resource's records, wired to
> your app's security.

## When to use

Override these hooks whenever a panel should not be fully open — to integrate
Symfony Security (Voters / `isGranted`), per-tenant rules, ownership checks, or a
read-only mode. The default is **open** (everything allowed): Atrium ships no
opinion about your security model, so you opt in by overriding the hooks. When
you do, the rule is enforced in three places automatically:

- **Page boundary** — the controller returns **403** for a denied list, create or
  edit page.
- **Form save** — the save action re-checks `canCreate()` / `canEdit()`
  server-side, so a crafted request can't bypass the page guard.
- **Table actions** — the built-in Edit / Delete / "New" actions are **hidden**
  when not permitted and **refuse to run** if triggered anyway; a bulk delete acts
  only on the records the user may delete.

## Example

```php
use Atrium\Resource\AdminResource;
use App\Entity\Article;

final class ArticleResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Article::class;
    }

    public function canViewAny(): bool
    {
        return $this->security->isGranted('ROLE_EDITOR');
    }

    public function canCreate(): bool
    {
        return $this->security->isGranted('ROLE_EDITOR');
    }

    public function canEdit(object $record): bool
    {
        return $record instanceof Article
            && $this->security->isGranted('ARTICLE_EDIT', $record);
    }

    public function canDelete(object $record): bool
    {
        return $this->security->isGranted('ARTICLE_DELETE', $record);
    }
}
```

Inject `Symfony\Bundle\SecurityBundle\Security` into your resource (resources are
autowired services) to reach `isGranted()`.

## API reference

| Method | Description |
| --- | --- |
| `canViewAny(): bool` | May the user open the resource's list page? Gates the page and navigation. |
| `canCreate(): bool` | May the user create a record? Gates the create page, the form save, and the "New" header action. |
| `canEdit(object $record): bool` | May the user edit this record? Gates the edit page, the form save, and the Edit action. |
| `canDelete(object $record): bool` | May the user delete this record? Gates the Delete action (record and bulk). |
| `canView(object $record): bool` | May the user view this record? (Used by custom `view` actions.) |
| `can(string $ability, ?object $record = null): bool` | Dispatch a named ability (`viewAny`, `create`, `edit`, `delete`, `view`) to the hook above. Record-scoped abilities deny without a record; an unknown ability is allowed. |

All default to `true`. Override only the ones you need.

### Authorizing custom actions

The built-in table actions declare an ability for you (`EditAction` → `edit`,
`DeleteAction` → `delete`, `CreateAction` → `create`). A **custom** action opts
into the same gating with `Action::authorize()`:

```php
use Atrium\Action\Action;

Action::make('publish')
    ->authorize('edit') // hidden + refused unless canEdit($record) is true
    ->action(fn (object $record, $writer) => /* … */);
```

The table hides and refuses to run an action whose ability the resource denies.
An action with no ability is always allowed (gate it yourself with `visible()`).

## See also

- [Record lifecycle hooks](lifecycle-hooks.md)
- [Actions](../actions/)
