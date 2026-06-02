# Actions

> A reusable, view-agnostic button — a link or a server-side operation — with a
> label, style, optional confirmation and authorization. The same `Action` backs
> row, header and bulk buttons.

## When to use

An `Action` is anything the user can *do*: open a page, toggle a flag, delete a
record, run a job. You configure one builder and place it wherever it belongs — a
table row, the header above a table, or the bulk bar over a selection (see
[Table actions](../tables/actions.md)). Atrium ships the common CRUD ones; you add
your own for domain operations.

There are two kinds:

- A **link action** resolves a URL (`->url(...)`) — a plain navigation.
- A **server action** carries a handler (`->action(...)`) that Atrium runs
  server-side, with an optional confirmation step.

```php
use Atrium\Action\Action;
use Atrium\DataProvider\DataWriterInterface;

// A link.
Action::make('view')->label('View on site')->icon('document')
    ->url(fn (object $record): string => '/articles/'.$record->slug);

// A confirmed server action.
Action::make('publish')->label('Publish')->icon('check')->color('green')
    ->requiresConfirmation()
    ->action(function (object $record, DataWriterInterface $writer): void {
        $record->status = 'published';
        $writer->update($record);
    });
```

## Building an action

| Method | Description |
| --- | --- |
| `make(string $name): static` | Create the action (the name identifies it). |
| `label(string): static` | Button label (defaults to a humanised name). |
| `icon(?string): static` | Optional icon. |
| `color(string): static` | Semantic colour: `gray`, `primary`, `red`, `green`, `amber`, `sky`. |
| `button()` / `link()` / `iconButton()` | Render style: filled button, text link (default), or icon-only. |
| `badge(int\|string\|null): static` | A small badge on the trigger. |
| `url(string\|Closure): static` | Make it a **link**; a closure receives the record. |
| `action(Closure): static` | Make it a **server action**; see handler signatures below. |
| `requiresConfirmation(bool = true): static` | Prompt before running. |
| `confirmationMessage(string): static` | Custom confirmation text (implies confirmation). |
| `visible(bool\|Closure): static` / `hidden(...)` | Show conditionally; a closure receives the record. |
| `authorize(string $ability): static` | Gate behind a resource ability (see below). |

## Server action handlers

The host passes the handler what makes sense in context. For a **record** action
(a table row) the handler receives the record and the data writer:

```php
->action(function (object $record, DataWriterInterface $writer): void {
    // mutate and persist $record
})
```

For a **bulk** action it receives the list of selected records:

```php
->action(function (array $records, DataWriterInterface $writer): void {
    foreach ($records as $record) { /* ... */ }
})
```

Header actions are subject-less (they sit above the table), so they are typically
links — for "create"-style buttons, prefer the built-in `CreateAction`.

## Confirmation

`requiresConfirmation()` (or `confirmationMessage('…')`) shows a server-driven
prompt before the handler runs — no client JavaScript. The prompt inherits the
action's label and colour, so a red **Delete** reads as destructive automatically.

## Authorization

`authorize('ability')` gates an action behind a [resource
ability](../resources/authorization.md). When the resource's `can('ability',
$record)` denies it, the action is **hidden and refuses to run** (checked again at
execution, since Live endpoints are directly POST-able). The built-in table
actions set their ability for you (`edit`, `delete`, `create`).

```php
Action::make('archive')->authorize('edit')->action(/* ... */);
```

Visibility (`visible()`) and authorization compose: a row-bound `visible()` closure
decides per record, while `authorize()` enforces permission.

## Grouping — `ActionGroup`

Collapse several actions into a single dropdown with `ActionGroup`:

```php
use Atrium\Action\ActionGroup;

ActionGroup::make([
    Action::make('duplicate')->icon('copy')->action(/* ... */),
    Action::make('archive')->icon('archive')->authorize('edit')->action(/* ... */),
])->label('More');
```

A group hides any child the user isn't authorized for, and disappears entirely if
none remain.

## Built-in table actions

Ready-made actions for the standard CRUD verbs, already wired with the right
ability, icon and confirmation:

| Action | Role | Notes |
| --- | --- | --- |
| `CreateAction` | header | The "New" button (link to the create page); ability `create`. |
| `EditAction` | record | Link to the edit page; ability `edit`. |
| `DeleteAction` | record | Confirmed delete; ability `delete`. |
| `BulkDeleteAction` | bulk | Confirmed delete of the selection; ability `delete`. |

A fresh [`TableConfiguration`](../tables/table-configuration.md) already includes
`EditAction` and `CreateAction`; add `DeleteAction` / `BulkDeleteAction` (and your
own) as needed.

## See also

- [Table actions](../tables/actions.md) — placing actions as row/header/bulk
- [Authorization](../resources/authorization.md) — the abilities `authorize()` checks
- [Action lifecycle hooks](../resources/lifecycle-hooks.md) — `beforeAction` / `afterAction`
