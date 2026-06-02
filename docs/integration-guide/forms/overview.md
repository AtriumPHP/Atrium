# Forms

> Describe a create/edit form as a PHP schema of fields and layout — Atrium
> renders it, validates input, reacts to changes, and persists the result.

## When to use

Every resource that can be created or edited has a form, defined by the `form()`
method. You return a `Schema` — a tree of **fields** (inputs) and **layout
components** (grids, sections, tabs) — and Atrium turns it into a reactive
`Form` Live Component over your entity. The same schema serves both the create
and the edit screen; the `$operation` (`'create'` / `'edit'`) is available to any
hook that needs to tell them apart.

## A first form

Add `form()` to your resource and list the fields:

```php
use Atrium\Form\Field\SelectField;
use Atrium\Form\Field\TextField;
use Atrium\Form\Field\TextareaField;
use Atrium\Form\Schema;

public function form(Schema $schema): Schema
{
    return $schema->fields([
        TextField::make('title')->required()->maxLength(120),
        TextField::make('slug')->required(),
        TextareaField::make('excerpt')->rows(3),
        SelectField::make('status')->options([
            'draft' => 'Draft',
            'published' => 'Published',
        ]),
    ]);
}
```

`fields()` is the shorthand for a flat list of fields. To mix fields with layout
components (grids, sections, tabs), use `components()` instead — see
[Layout](layout.md).

## How a field maps to your entity

A field's **name** is a property path on the entity, read and written through
Symfony's PropertyAccessor:

- On **edit**, the field is filled from `$entity->getName()` / `$entity->name`.
- On **save**, the (validated, normalised) value is written back the same way.

So `TextField::make('title')` round-trips `Article::$title`. A field whose name
doesn't map to a writable property simply isn't persisted — handy with
[`dehydrated(false)`](#fields-that-are-not-persisted) for inputs that drive other
fields but aren't stored.

Values are converted at the edges: each field type defines how a model value
becomes a form value and back (e.g. a `DateTimeField` ↔ `DateTimeImmutable`), so
your entity keeps its real types.

## The save flow

When the form is submitted, Atrium:

1. runs [`mutateFormDataBeforeValidate`](../resources/lifecycle-hooks.md),
2. **validates** every visible field (Symfony constraints); on failure it keeps
   the entered values and shows inline errors — the component never breaks,
3. runs `afterValidate`,
4. runs `mutateFormDataBeforeSave`, writes the values onto the entity, and
   persists inside a transaction (`beforeSave` → write → `afterSave`).

All of these are [resource lifecycle hooks](../resources/lifecycle-hooks.md) —
the form itself is generic. After a successful save the page either redirects
(the default List/Create/Edit pages supply the URL) or shows a success notice.

## Reactivity

Fields are static by default. Mark a field `->live()` and it triggers a server
round-trip on change, so dependents can update — a classic dependent select, a
field revealed by a toggle, a slug derived from a title. See
[Reactive fields](reactivity.md) for `live()`, `afterStateUpdated()`, and the
`Get`/`Set` accessors.

## Fields that are not persisted

`->dehydrated(false)` keeps a field in the form — rendered, validated, readable by
reactive callbacks — but **not** written to the entity on save. Use it for a
confirmation field, or an input that only computes another field's value.

```php
TextField::make('passwordConfirmation')->dehydrated(false)->same('password'),
```

## Common field options

Every field shares these (from the base `Field`):

| Method | Description |
| --- | --- |
| `make(string $name): static` | Create the field; `$name` is the entity property path. |
| `label(string): static` | Override the humanised label. |
| `required(bool = true): static` | Mark required (adds a `NotBlank` constraint). |
| `default(mixed): static` | Default value for the create form. |
| `help(string): static` | Helper text shown under the field. |
| `placeholder(?string): static` | Placeholder (text-like fields). |
| `disabled(bool = true): static` | Render read-only; not persisted. |
| `autofocus(bool = true): static` | Focus on load. |
| `live(bool = true): static` | Re-render on change (see [Reactivity](reactivity.md)). |
| `dehydrated(bool = true): static` | `false` = shown/validated but not saved. |
| `hiddenLabel(bool = true): static` | Keep the label for screen readers, hide it visually. |
| `inlineLabel(bool = true): static` | Render the label beside the input. |
| `columnSpan(int\|string): static` / `columnSpanFull(): static` | Width within a [grid](layout.md). |
| `visible(bool\|Closure): static` / `hidden(...)` | Conditional display (see [Reactivity](reactivity.md)). |
| `visibleOn(string\|array)` / `hiddenOn(...)` | Show only on `create` / `edit`. |
| `rules(array $constraints): static` | Attach Symfony constraints (see [Validation](validation.md)). |

## See also

- [Fields](fields.md) — every field type and its options
- [Layout](layout.md) — grids, sections, fieldsets, flex
- [Tabs & wizards](tabs-and-wizards.md) — multi-panel and multi-step forms
- [Validation](validation.md) — constraints and fluent rules
- [Reactive fields](reactivity.md) — `live()`, `afterStateUpdated()`, `Get`/`Set`
- [Content](content.md) — non-input content (text, images, lists) in a form
