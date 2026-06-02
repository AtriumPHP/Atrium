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

1. `mutateFormDataBeforeValidate($data, $operation)` — clean raw input,
2. validate the submitted fields,
3. `afterValidate($data, $operation)` — react to valid data (side-effect only),
4. `mutateFormDataBeforeSave($data, $operation)` — your transform,
5. write the (possibly mutated) field values onto the entity,
6. **in one transaction:**
   1. `beforeSave($entity, $operation)` — set extra properties here,
   2. `handleRecordCreation($entity, $writer)` / `handleRecordUpdate($entity, $writer)` — persist,
   3. `afterSave($entity, $operation)`.

`$operation` is `'create'` or `'edit'`, so one hook can serve both. If validation
fails, Atrium stops after step 2 (no `afterValidate`, no write) and surfaces the
errors. When the form *fills*, `mutateFormDataBeforeFill($data, $operation)` runs
for **both** operations — on edit it receives the record's values, on create the
fields' defaults (so it can seed a create form). Delete runs `beforeDelete` /
`afterDelete` around the built-in delete actions (record and bulk).

### Atomic persistence

Step 4 runs inside a transaction (`DataWriterInterface::transactional()`), so a
failing `afterSave` (or a domain-event subscriber it triggers) **rolls the write
back** rather than leaving a half-saved record. The same applies to deletes. The
in-memory array writer has no real transaction; the Doctrine writer uses
`wrapInTransaction`.

### Custom persistence

`handleRecordCreation` / `handleRecordUpdate` own the actual write. By default
they call the [data writer](../data/), but you can override either to persist
through your own service, a command bus, or an API — without rewriting the form:

```php
public function handleRecordCreation(object $record, DataWriterInterface $writer): void
{
    // Route the write through a domain service instead of the writer.
    $this->articlePublisher->publish($record);
}
```

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
| `mutateFormDataBeforeFill(array $data, string $operation): array` | Transform the data that fills the form. Runs on **create** (defaults) and **edit** (the record). |
| `mutateFormDataBeforeValidate(array $data, string $operation): array` | Clean the raw submitted data before validation sees it (trim, coerce). |
| `afterValidate(array $data, string $operation): void` | React to the validated data (side-effect only). Runs only on a valid submit. |
| `mutateFormDataBeforeSave(array $data, string $operation): array` | Transform submitted form data before it is written to the entity. `$operation` is `create` or `edit`. |
| `beforeSave(object $record, string $operation): void` | Runs after fields are applied, before persistence (inside the transaction). Set non-field properties here. |
| `handleRecordCreation(object $record, DataWriterInterface $writer): void` | Persist a new record. Defaults to `$writer->create()`; override to persist your own way. |
| `handleRecordUpdate(object $record, DataWriterInterface $writer): void` | Persist an updated record. Defaults to `$writer->update()`; override to persist your own way. |
| `afterSave(object $record, string $operation): void` | Runs after the entity is persisted (still inside the transaction). |
| `beforeDelete(object $record): void` | Runs before a record is deleted (built-in delete actions). |
| `afterDelete(object $record): void` | Runs after a record is deleted. |
| `beforeAction(string $action, object $record): void` | Runs before **any** row action's handler. `$action` is the action name. |
| `afterAction(string $action, object $record): void` | Runs after any row action's handler. |
| `beforeBulkAction(string $action, array $records): void` | Runs before a bulk action's handler, with the records it will act on. |
| `afterBulkAction(string $action, array $records): void` | Runs after a bulk action's handler. |

The `mutate*` hooks default to returning the data unchanged; the `before*` /
`after*` hooks default to no-ops.

`beforeAction` / `afterAction` (and the bulk pair) are the generic seam around
*every* table action — keyed by name, so one hook can audit-log or bust caches for
any action; `beforeDelete` / `afterDelete` are the delete-specific convenience.
All of these run **inside the action's transaction**, so throwing from one rolls
the action back.

All persistence still goes through the
[data writer](../data/), so these hooks compose with any storage backend.

## See also

- [Authorization](authorization.md)
- [Query scoping](../data/query-scoping.md)
- [Data providers & writer](../data/)
