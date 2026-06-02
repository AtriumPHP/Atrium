# Custom field types

Atrium ships a set of built-in form fields (`TextField`, `TextareaField`,
`NumberField`, `CheckboxField`, `SelectField`, `DateField`, `DateTimeField`).
When you need something they don't cover — a country picker, a color swatch, a
money input, a relation autocomplete — you define your own field type **entirely
in your application**. No change to Atrium's form renderer is required.

This works because the form renderer never switches on field type. Each field
declares the Twig template that renders its widget, and the wrapper simply
`include`s it. Adding a field is: a PHP class + a Twig template.

## The contract

A custom field is a class extending `Atrium\Form\Field\Field` (or any concrete
built-in field, to reuse its behaviour). The base class is fluent and
Doctrine-agnostic; you inherit `make()`, `label()`, `required()`, `live()`,
`disabled()`, `help()`, `default()`, `rules()` and the matching getters.

You typically override three things:

| Method | Purpose | Default |
| --- | --- | --- |
| `getType(): string` | Semantic id for the widget (used to derive the default template). | abstract — must implement |
| `getTemplate(): string` | The Twig template that renders the widget. | `@Atrium/components/form/widget/{type}.html.twig` |
| `rendersOwnLabel(): bool` | Return `true` if the widget draws its own `<label>` (e.g. an inline checkbox); the wrapper then skips its label. | `false` |

For input/output conversion (string ⇆ model value) override:

| Method | Purpose | Default |
| --- | --- | --- |
| `normalize(mixed $value): mixed` | Submitted form value → model value (e.g. `"3.5"` → `3.5`, `""` → `null`). | identity |
| `toFormValue(mixed $value): mixed` | Model value → form (display) string. | identity |

## What the widget template receives

The field wrapper (`@Atrium/components/form/field.html.twig`) computes the shared
context and passes it to your template:

| Variable | What it is |
| --- | --- |
| `field` | Your field instance — call its getters (`field.getLabel()`, `field.isDisabled()`, `field.getPlaceholder()`, …). |
| `name` | The field name; the input id convention is `atrium_{{ name }}`. |
| `model` | The Live Component data-binding expression. Put it on the input as `data-model="{{ model }}"` — this is what makes the field reactive and persists its value across re-renders. |
| `inputClass` | The standard Tailwind input classes (border, focus ring, dark-mode variants, error state). Reuse it so your field matches the rest of the form. |

Inside the template, `this` is the `Atrium:Form` Live Component, so you have:

- `this.getValue(name)` — the current value of this field.
- `this.getError(name)` — the validation error string, if any (the wrapper
  already renders it below the widget; you rarely need this directly).
- `this.optionsFor(field)` — for select-like fields, the resolved
  `value => label` option map (honours `optionsUsing()` reactive callbacks).

> **Important Twig gotcha.** Field getters are fluent setters' siblings —
> `field.label` would resolve the *setter*. Always call the explicit getter:
> `field.getLabel()`, `field.getPlaceholder()`, `field.isRequired()`.

## Example: a country select

This is the field used in the playground. It extends `SelectField` to reuse its
option handling and string normalisation, preloads a country list, and ships its
own template.

```php
<?php

declare(strict_types=1);

namespace App\Admin\Field;

use Atrium\Form\Field\SelectField;

final class CountrySelect extends SelectField
{
    private const array COUNTRIES = [
        'us' => '🇺🇸 United States',
        'gb' => '🇬🇧 United Kingdom',
        'pl' => '🇵🇱 Poland',
        // …
    ];

    public static function make(string $name): static
    {
        $field = parent::make($name);
        $field->options(self::COUNTRIES);
        $field->placeholder('Select a country…');

        return $field;
    }

    public function getType(): string
    {
        return 'country';
    }

    public function getTemplate(): string
    {
        return 'admin/fields/country.html.twig';
    }
}
```

The template lives in your app's `templates/` directory. It receives the same
context every Atrium widget gets:

```twig
{# templates/admin/fields/country.html.twig #}
{% set selected = this.getValue(name) %}
{% set options = this.optionsFor(field) %}

<div class="mt-1 flex items-center gap-2">
    <span class="text-lg" aria-hidden="true">🌍</span>
    <select id="atrium_{{ name }}" data-model="{{ model }}" class="{{ inputClass }} mt-0">
        {% if field.getPlaceholder() is not null %}
            <option value="">{{ field.getPlaceholder() }}</option>
        {% endif %}
        {% for value, label in options %}
            <option value="{{ value }}" {{ selected == value ? 'selected' }}>{{ label }}</option>
        {% endfor %}
    </select>
</div>
```

Use it in a resource form exactly like a built-in field:

```php
public function form(Schema $schema): Schema
{
    return $schema->fields([
        TextField::make('name')->required(),
        CountrySelect::make('country')->label('Country of origin'),
    ]);
}
```

## Notes

- **Reactivity.** Set `->live()` on your field if changing it should trigger a
  server re-render (so dependent fields can update). The `data-model` binding is
  what carries the value; `live()` controls whether edits round-trip immediately.
- **Validation.** Attach Symfony constraints with `->rules([...])`, or override
  `getConstraints()` for type-specific defaults (as `CheckboxField` does with
  `IsTrue`). The form component validates each field's constraints on save.
- **Styling.** Reuse `inputClass` for consistency, including dark mode. If you
  add brand-new Tailwind classes that Atrium's precompiled CSS doesn't already
  ship, you'll need Tailwind scanning your own templates — for app-level classes
  this is handled by your app's normal asset pipeline.
- **Extending built-ins.** All concrete field types are non-`final`, so you can
  subclass `SelectField`, `TextField`, etc. to reuse normalisation and only
  override presentation. Extend the abstract `Field` directly when you need a
  genuinely new widget with no built-in analogue.
