# Reactive fields

> Make a form respond to itself — reveal fields, recompute values, and load
> dependent options as the user types, all server-side with no custom JavaScript.

## When to use

By default fields are independent and static. Make a field `->live()` and changing
it triggers a server round-trip that re-renders the form, so other fields can
react. Three things build on that:

- **Conditional visibility** — show or hide a field based on another's value.
- **`afterStateUpdated`** — run code when a field changes (e.g. derive a slug).
- **Dependent options** — recompute a select's choices from the current state.

All of it runs on the server through Live Components; you write only PHP closures.

## `live()`

Marking the *source* field live is what makes the form re-render on its change:

```php
SelectField::make('status')->live(),
```

A field that other fields depend on must be `->live()`. Fields that merely *react*
(the dependents) don't need it.

## Reading state with `Get`

Reactive closures receive a `Get` accessor — invoke it with a field name to read
that field's current value: `$get('status')`. It's how a closure sees the rest of
the form.

## Conditional visibility

Pass a closure to `visible()` (or `hidden()`); it receives `Get` and returns a
bool. The field — or whole [layout container](layout.md) — appears only when the
condition holds. A hidden field is fully removed: not rendered, not validated, not
persisted.

```php
use Atrium\Form\Get;

SelectField::make('status')
    ->options(['draft' => 'Draft', 'published' => 'Published'])
    ->live(),

DateTimeField::make('publishedAt')
    ->visible(fn (Get $get): bool => 'published' === $get('status')),
```

For create-vs-edit, use the operation forms instead of a closure:

```php
TextField::make('password')->visibleOn('create'),
TextField::make('slug')->hiddenOn('create'),
```

## Reacting with `afterStateUpdated`

`afterStateUpdated()` runs a callback whenever the field's value changes. The
callback gets the new value, a `Get` (read other fields), and a `Set` (write other
fields). The classic use is deriving one field from another:

```php
use Atrium\Form\Get;
use Atrium\Form\Set;

TextField::make('title')
    ->live()
    ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
        $set('slug', (new AsciiSlugger())->slug((string) $state)->lower()->toString());
    }),

TextField::make('slug'),
```

Now typing a title fills the slug. `Set` is invoked as `$set('field', $value)`;
`Get` as `$get('field')`.

## Dependent options

A select whose choices depend on another field combines a live parent with
`optionsUsing()` on the child — the callback receives the whole form state and
returns the `value => label` choices:

```php
SelectField::make('country')->options($this->countries())->live(),

SelectField::make('city')->optionsUsing(
    fn (array $state): array => $this->citiesForCountry($state['country'] ?? null),
),
```

When `country` changes, the form re-renders and `city`'s options are recomputed.

## How it works (and why it's safe)

Each interaction is a server round-trip: the changed value is sent up, your
closures run on the server, and the morphed HTML comes back. There is no
client-side state to trust — validation, visibility and options are all decided
server-side every render, so a hidden field stays unvalidated/unpersisted even if
the client tries to send it.

## See also

- [Fields](fields.md) — `SelectField::optionsUsing()`, `live()`, the field types
- [Layout](layout.md) — containers support the same `visible()` closures
- [Validation](validation.md) — hidden fields are skipped by validation
- [Lifecycle hooks](../resources/lifecycle-hooks.md) — server-side data hooks around save
