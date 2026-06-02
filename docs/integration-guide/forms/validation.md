# Validation

> Validate form input with Symfony constraints — attached fluently for common
> rules, or explicitly for anything else. Invalid input never breaks the form.

## When to use

Atrium validates a form on save using the Symfony Validator. You attach rules per
field: fluent helpers cover the everyday cases (required, lengths, email, numeric
bounds, cross-field matches), and `rules([...])` lets you attach **any** Symfony
constraint. Validation runs on every visible field; a hidden field
([conditionally hidden](reactivity.md) or `HiddenField`) is not validated.

When validation fails, the form **keeps the entered values**, shows an inline
error under each offending field, and reveals the tab/section holding the first
error — the Live Component never breaks or loses input.

## Fluent rules

The quickest path — these read well and attach the right constraint for you:

```php
use Atrium\Form\Field\NumberField;
use Atrium\Form\Field\TextField;

TextField::make('title')->required()->minLength(3)->maxLength(120),
TextField::make('email')->email(),                 // validates as an email
TextField::make('homepage')->url(),                // validates as a URL
TextField::make('slug')->regex('/^[a-z0-9-]+$/', 'Lowercase, digits and dashes only.'),
NumberField::make('quantity')->integer()->min(1)->max(99),
```

| Helper | Applies to | Rule |
| --- | --- | --- |
| `required(bool = true)` | any field | Not blank. |
| `minLength(int)` / `maxLength(int)` / `length(int)` | text | String length. |
| `regex(string $pattern, ?string $message)` | text | Pattern match. |
| `email()` / `url()` | `TextField` | Valid email / URL (and the matching input type). |
| `min(int\|float)` / `max(int\|float)` | `NumberField` | Numeric bounds. |
| `same(string $field, ?string $message)` | any field | Must equal another field's value. |
| `different(string $field, ?string $message)` | any field | Must differ from another field's value. |

### Cross-field rules

`same()` / `different()` compare against a **sibling field** in the same form —
something a standalone constraint can't see. The classic case is a confirmation:

```php
TextField::make('password')->required(),
TextField::make('passwordConfirmation')
    ->dehydrated(false)            // shown & validated, but not saved
    ->same('password', 'The passwords must match.'),
```

## Arbitrary constraints

For anything the helpers don't cover, attach Symfony constraints directly with
`rules()`. They compose with the fluent helpers and with `required()`.

```php
use Symfony\Component\Validator\Constraints as Assert;

TextField::make('username')->required()->rules([
    new Assert\Length(min: 3, max: 30),
    new Assert\Regex('/^\w+$/'),
]),

NumberField::make('discount')->rules([
    new Assert\Range(min: 0, max: 100),
]),
```

`required()` adds a `NotBlank` for you; everything in `rules()` is applied in
addition. Any constraint from `symfony/validator` (or your own custom constraint)
works.

## How it runs

On save, each **visible** field's submitted value is normalised by its type, then
validated against its constraint set. The first violation per field becomes that
field's error message. Only after every field passes does Atrium proceed to
persist (see the [save flow](overview.md#the-save-flow)). For pre-validation
input cleaning or post-validation reactions, use the
[`mutateFormDataBeforeValidate` / `afterValidate`](../resources/lifecycle-hooks.md)
resource hooks.

## See also

- [Fields](fields.md) — per-type options, including the input-purpose shortcuts
- [Reactive fields](reactivity.md) — conditional visibility (hidden = not validated)
- [Lifecycle hooks](../resources/lifecycle-hooks.md) — `mutateFormDataBeforeValidate`, `afterValidate`
