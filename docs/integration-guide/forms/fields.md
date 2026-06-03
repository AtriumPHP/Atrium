# Fields

> The input types you put in a form schema — text, numbers, choices, dates,
> toggles, colours, and the structured Tags / Key-value editors.

## When to use

Fields are the leaves of a [form schema](overview.md). Every field shares the
[common options](overview.md#common-field-options) (`label`, `required`,
`default`, `help`, `disabled`, `live`, validation, visibility…); this page covers
what each **type** adds on top. Reach for the type that matches the entity
property: a string → `TextField`, an int → `NumberField`, an enum/choice →
`SelectField`, a bool → `ToggleField`, and so on.

```php
use Atrium\Form\Field\{TextField, NumberField, SelectField, ToggleField, DateTimeField};

$schema->fields([
    TextField::make('title')->required()->maxLength(120),
    NumberField::make('readingMinutes')->integer()->min(1),
    SelectField::make('status')->options(['draft' => 'Draft', 'published' => 'Published']),
    ToggleField::make('featured'),
    DateTimeField::make('publishedAt'),
]);
```

## Text & numbers

### `TextField`

A single-line text input. Adds input-purpose shortcuts that also attach the
matching validation:

| Method | Description |
| --- | --- |
| `email(): self` | Render an email input and validate as an email. |
| `url(): self` | Render a URL input and validate as a URL. |
| `placeholder(?string): self` | Placeholder text. |
| `maxLength(int)` / `minLength(int)` / `length(int)` | Length constraints. |
| `regex(string $pattern, ?string $message)` | Match a pattern. |
| `same(string $field)` / `different(string $field)` | Cross-field comparison. |

```php
TextField::make('email')->email()->required(),
TextField::make('slug')->required()->regex('/^[a-z0-9-]+$/', 'Lowercase, digits and dashes only.'),
```

### `TextareaField`

A multi-line text input.

| Method | Description |
| --- | --- |
| `rows(int): self` | Visible row count. |

### `NumberField`

A numeric input. Normalises to `int` or `float`.

| Method | Description |
| --- | --- |
| `integer(bool = true): static` | Constrain to whole numbers (and cast to int). |
| `min(int\|float)` / `max(int\|float)` | Numeric bounds (validated). |

## Choices

### `SelectField`

A dropdown of mutually-exclusive options.

| Method | Description |
| --- | --- |
| `options(array $options): self` | `value => label` choices. |
| `optionsUsing(callable $callback): self` | Compute options from the current form state — for **dependent** selects. |
| `placeholder(?string): self` | An empty-choice prompt. |

A dependent select combines `live()` on the parent with `optionsUsing()` on the
child (the callback receives the whole form state):

```php
SelectField::make('country')->options($countries)->live(),
SelectField::make('city')->optionsUsing(
    fn (array $state): array => $this->citiesFor($state['country'] ?? null),
),
```

See [Reactive fields](reactivity.md) for the full pattern.

### `RadioField`

The same option API as `SelectField`, rendered as a vertical radio list. Use it
for a short set of choices you want visible at once.

### `ToggleButtonsField`

The same option API, rendered as a horizontal segmented button group. Good for 2–4
choices (e.g. a layout or size).

## Booleans

### `CheckboxField`

A single checkbox for a boolean property.

### `ToggleField`

A checkbox rendered as a switch (same behaviour, nicer affordance). Prefer it for
on/off settings.

```php
ToggleField::make('featured'),
CheckboxField::make('acceptsTerms')->dehydrated(false)->required(),
```

## Dates & colour

### `DateField`

A date picker; round-trips a `DateTimeImmutable` (date only).

### `DateTimeField`

A date-and-time picker; round-trips a `DateTimeImmutable`. (Extends `DateField`.)

### `ColorField`

A colour picker; round-trips a hex string (e.g. `#6366f1`).

```php
DateTimeField::make('publishedAt')->label('Publish date'),
ColorField::make('accentColor')->label('Accent colour'),
```

## Structured fields

### `TagsField`

Edits a **list of strings** (`list<string>`) — a tag/keyword editor.

| Method | Description |
| --- | --- |
| `placeholder(?string)` | Prompt shown in the entry box. |

```php
TagsField::make('tags')->placeholder('php, symfony, ux'),
```

### `KeyValueField`

Edits a **string-to-string map** (`array<string, string>`) — arbitrary metadata as
key/value rows the user can add and remove.

```php
KeyValueField::make('metadata')->label('Custom metadata'),
```

## Hidden state

### `HiddenField`

Carried in the form state — set, read by reactive callbacks, and persisted — but
never rendered. Use it to keep a value (a source flag, a token) alongside the
visible fields.

```php
HiddenField::make('source')->default('admin'),
```

## Custom field types

Field types are open. Subclass `Field` (or an existing type), declare your
widget's `getType()` and ship a widget template at
`@Atrium/components/form/widget/{type}.html.twig` — the form renderer includes
whatever template the field declares, so a third-party bundle can add a field
without touching the core. Override `normalize()` / `toFormValue()` to convert
between the form value and the model value.

See **[Custom field types](custom-fields.md)** for the full guide — the contract,
what the widget template receives, and a complete worked example.

## See also

- [Custom field types](custom-fields.md) — define your own field (full guide)
- [Form overview](overview.md) — schema, the save flow, common options
- [Validation](validation.md) — constraints and the fluent rule helpers
- [Reactive fields](reactivity.md) — `live()`, `afterStateUpdated()`, dependent options
- [Layout](layout.md) — arranging fields in grids, sections and tabs
