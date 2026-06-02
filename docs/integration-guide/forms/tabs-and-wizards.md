# Tabs & wizards

> Split a long form into tabbed panels or a step-by-step wizard — both
> server-driven, so the active panel survives re-renders with no client state.

## When to use

When a form has too many fields for one screen, group them:

- **Tabs** — independent panels the user switches between freely (e.g. *Content* /
  *Publishing* / *Metadata*).
- **Wizard** — ordered steps the user moves through in sequence (e.g. a guided
  create flow).

Both are [layout components](layout.md): you nest fields (and other layout) inside
each tab or step. The active tab/step is held on the server, so switching keeps
every field's in-progress value, and a failed save automatically reveals the
panel holding the first error.

## Tabs

```php
use Atrium\Form\Field\TextField;
use Atrium\Form\Field\ToggleField;
use Atrium\Layout\Tab;
use Atrium\Layout\Tabs;

$schema->components([
    Tabs::make()->columnSpanFull()->tabs([
        Tab::make('Content')->icon('document')->columns(2)->schema([
            TextField::make('title')->required(),
            TextField::make('slug')->required(),
        ]),
        Tab::make('Publishing')->schema([
            ToggleField::make('featured'),
        ]),
        Tab::make('Metadata')->icon('tag')->badge('2')->schema([
            // ...
        ]),
    ]),
]);
```

| Component | Method | Description |
| --- | --- | --- |
| `Tabs` | `make(): self` | Create the tab container. |
| | `tabs(array $tabs): self` | The list of `Tab`s. |
| | `id(string): self` | Stable id (set it if you render two tab sets, so their active state doesn't clash). |
| `Tab` | `make(string $label): self` | A panel with a label. |
| | `icon(?string): self` | Optional icon on the tab. |
| | `badge(int\|string\|null): self` | Optional badge on the tab. |
| | `columns(int\|array)` / `schema(array)` | Lay out its contents (same as any container). |

## Wizards

A wizard renders the same field tree as a sequence of steps with a progress
header. Atrium upgrades the form component to the wizard automatically when the
schema contains a `Wizard`.

```php
use Atrium\Layout\Step;
use Atrium\Layout\Wizard;

$schema->components([
    Wizard::make()->steps([
        Step::make('Account')->icon('user')->description('Who is signing up')->schema([
            TextField::make('email')->email()->required(),
        ]),
        Step::make('Profile')->schema([
            TextField::make('displayName')->required(),
        ]),
        Step::make('Confirm')->schema([
            // ...
        ]),
    ]),
]);
```

| Component | Method | Description |
| --- | --- | --- |
| `Wizard` | `make(): self` | Create the wizard container. |
| | `steps(array $steps): self` | The ordered list of `Step`s. |
| | `id(string): self` | Stable id for the active-step state. |
| `Step` | `make(string $label): self` | A step with a label. |
| | `icon(?string): self` | Optional icon in the progress header. |
| | `description(?string): self` | Sub-label under the step title. |
| | `columns(int\|array)` / `schema(array)` | Lay out the step's fields. |

Validation runs per step as the user advances, so they can't move past a step with
invalid input; the final submit persists the whole form.

## See also

- [Layout](layout.md) — grids, sections and fieldsets inside a tab or step
- [Fields](fields.md) — the inputs you place in each panel
- [Validation](validation.md) — how per-step validation surfaces errors
