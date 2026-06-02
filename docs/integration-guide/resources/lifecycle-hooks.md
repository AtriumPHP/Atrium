# Record lifecycle hooks

> Shape form data and run side effects around create, update and delete.

## When to use

Override these hooks to inject behaviour into the persistence path without
writing a custom form or controller: set a field the user doesn't edit (owner,
timestamps), derive a value (a slug from a title), transform a record before it
fills the edit form, or react after a write (audit log, cache busting, cleanup on
delete). They are the integration seam between Atrium's generic form/table and
your domain.

There are two kinds:

- **`mutate*` hooks** transform the form-state array (a `field name => value`
  map) — use them to change *field values*.
- **`before*` / `after*` hooks** receive the *entity* — use them to set
  non-field properties and run side effects.

## The save flow

On save, Atrium runs:

1. validate the submitted fields,
2. `mutateFormDataBeforeSave($data, $operation)` — your transform,
3. write the (possibly mutated) field values onto the entity,
4. `beforeSave($entity, $operation)` — set extra properties here,
5. persist via the data writer (`create` or `update`),
6. `afterSave($entity, $operation)`.

`$operation` is `'create'` or `'edit'`, so one hook can serve both. On the edit
form's *fill*, `mutateFormDataBeforeFill($data)` runs before the record populates
the form. Delete runs `beforeDelete` / `afterDelete` around the built-in delete
actions (record and bulk).

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

    // Derive a field value from another field before saving.
    public function mutateFormDataBeforeSave(array $data, string $operation): array
    {
        $data['slug'] = (new AsciiSlugger())->slug($data['title'] ?? '')->lower()->toString();

        return $data;
    }

    // Set properties the form does not expose.
    public function beforeSave(object $record, string $operation): void
    {
        if ($record instanceof Article && 'create' === $operation) {
            $record->author = $this->security->getUser();
            $record->createdAt = new \DateTimeImmutable();
        }
    }

    // React after the write.
    public function afterSave(object $record, string $operation): void
    {
        $this->cache->delete('articles');
    }

    public function afterDelete(object $record): void
    {
        $this->auditLog->record('article.deleted', $record);
    }
}
```

## API reference

| Method | Description |
| --- | --- |
| `mutateFormDataBeforeFill(array $data): array` | Transform a record's data before it fills the edit form. Runs on edit only. |
| `mutateFormDataBeforeSave(array $data, string $operation): array` | Transform submitted form data before it is written to the entity. `$operation` is `create` or `edit`. |
| `beforeSave(object $record, string $operation): void` | Runs after fields are applied, before persistence. Set non-field properties here. |
| `afterSave(object $record, string $operation): void` | Runs after the entity is persisted. |
| `beforeDelete(object $record): void` | Runs before a record is deleted (built-in delete actions). |
| `afterDelete(object $record): void` | Runs after a record is deleted. |

The `mutate*` hooks default to returning the data unchanged; the `before*` /
`after*` hooks default to no-ops. All persistence still goes through the
[data writer](../data/), so these hooks compose with any storage backend.

## See also

- [Authorization](authorization.md)
- [Data providers & writer](../data/)
