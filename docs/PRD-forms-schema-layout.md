# Atrium — Forms: Schema Tree, Layout & Field Capabilities (PRD addendum)

> **Status:** Proposed · **Type:** Addendum to [`docs/PRD.md`](./PRD.md)
> **Extends:** §8.3 Forms (`FRM`), §8.11 Layout (`LAY`), §9 DX contract
> **Supersedes nothing** — all existing `FRM-01..07` behaviour is preserved.

This document scopes the next evolution of Atrium's **form layer**, taking the
proven ideas from Filament's
[Forms](https://filamentphp.com/docs/5.x/forms/overview) and
[Schemas/Layouts](https://filamentphp.com/docs/5.x/schemas/layouts) docs and
adapting them to Atrium's architecture (server-driven Live Components, no client
framework, no Doctrine in core, the inline path stays valid).

It introduces two new requirement namespaces — **`SCH`** (schema tree & layout)
and **`FLD`** (new field types) — and extends the existing **`FRM`** namespace
from `FRM-08`. IDs are referenceable from commits/PRs exactly like the main PRD.

---

## 1. Motivation

Today `Atrium\Form\Schema` is a **flat `list<Field>`**, rendered as a single
vertical stack. This is fine for tiny forms but breaks down quickly:

- No way to place fields **side by side** (the form page is now full-width, which
  makes a single-column stack look sparse).
- No **grouping** — no sections with a heading/description, no fieldsets.
- Field visibility is all-or-nothing; there is no `visible()/hiddenOn()` to adapt
  the form to the current value or operation (create vs edit).
- The reactive surface is limited to `optionsUsing()`; there is no
  `afterStateUpdated()` to let one field mutate another (e.g. title → slug).
- Only the v1 field set exists (text, textarea, number, select, checkbox,
  date, datetime).

Filament's central abstraction is that a **Schema is a tree** whose nodes are
*either* fields *or* layout containers, every node carrying a `columnSpan`. That
single idea unlocks grids, sections, tabs, wizards and conditional layout. It is
the keystone change here; everything else composes onto it.

## 2. Goals

- Turn `Schema` into a **composable component tree** without breaking the
  existing flat `fields([...])` API or the inline resource path.
- Provide **responsive grid layout** (`Grid`, `Section`, `Fieldset`) configured
  in PHP, rendered with the bundle's precompiled Tailwind — **no consumer
  Tailwind config**, consistent with how the panel ships CSS today.
- Add **conditional visibility** and **cross-field reactivity** that ride on the
  Live Component re-render we already have.
- Add the **cheap, high-frequency field types** Filament has that we lack.
- Keep every new capability **optional and additive**; a resource that only calls
  `->fields([...])` keeps working unchanged.

## 3. Non-goals (v1 of this addendum)

- **Client-side (Alpine/JS) evaluation** — Filament's `hiddenJs()`,
  `visibleJs()`, `afterStateUpdatedJs()`, `JsContent`. These evaluate logic in the
  browser to skip a round-trip. They conflict with the **server-driven** rule
  (PRD §5.2, CLAUDE.md #1). Visibility/reactivity here are **server-evaluated**
  during the Live Component re-render. *Noted as a possible future opt-in for
  latency-sensitive widgets only.*
- **Container queries** (`gridContainer()`, `@md` breakpoints). Premature; revisit
  only after the standard responsive grid exists and density is a real problem.
- **Heavy field types** (Repeater, Builder, File upload, Rich/Markdown editor) are
  *catalogued* here (`FLD-10+`) for the roadmap but are **out of scope** for the
  first milestones.

---

## 4. Functional requirements

### 4.1 Schema tree & layout — `SCH`

- **SCH-01** A `Schema` is a **tree of schema components**. A schema component is
  either a **field** (`Atrium\Form\Field\Field`) or a **layout component**
  (`Atrium\Form\Layout\*`). Both implement a shared `SchemaComponent` contract
  exposing child components (a leaf field returns none).
- **SCH-02** `Schema::components([...])` accepts a mixed list of fields and layout
  components. `Schema::fields([...])` **remains valid** as the flat shortcut
  (single implicit full-width column) — `FRM-01` and §9a/§9b are unaffected.
- **SCH-03** **Grid** — `Grid::make(int|array $columns = 2)->schema([...])`.
  Integer = columns at `lg`+ (1 on smaller). Array =
  per-breakpoint (`sm`/`md`/`lg`/`xl`/`2xl`), e.g. `Grid::make(['md' => 2, 'xl' => 4])`.
- **SCH-04** **Section** — `Section::make('Heading')` with `->description(string)`,
  `->schema([...])`, `->columns(int|array)`, `->collapsible()`, `->collapsed()`,
  `->compact()`, `->aside()` (heading column beside content), `->icon(string)`.
  A bordered card with a header; the panel's standard card styling + dark mode.
- **SCH-05** **Fieldset** — `Fieldset::make('Label')->schema([...])->columns(2)`;
  a labelled bordered group (default 2-column). `->contained(false)` drops the
  border, keeping the label/grid.
- **SCH-06** **Column span / placement** — every schema component supports
  `->columnSpan(int|'full'|array)` and `->columnSpanFull()` to size itself within
  its parent grid. (Stretch: `->columnStart()`, `->columnOrder()`.)
- **SCH-07** **Nesting** — layout components nest arbitrarily (Section → Grid →
  fields). The renderer walks the tree recursively.
- **SCH-08** **Rendering** — each layout component declares its own Twig template
  (mirroring `Field::getTemplate()`), so custom layout components are an open
  extension point too. A recursive `schema` partial renders the tree: layout →
  container + recurse; field → the existing field widget pipeline.
- **SCH-09** **Collapsible state** is a client interaction with no data impact; it
  may be Stimulus-driven and need not round-trip (collapsing is the *one* place a
  tiny bit of local JS is acceptable, as pure progressive enhancement).
- **SCH-10** (Stretch) **Tabs** and **Wizard** (multi-step) layout components,
  building on the same tree; Wizard adds step validation + next/prev actions.

### 4.2 Field-level capabilities — `FRM` (continued)

- **FRM-08** **Conditional visibility** — `->visible(bool|Closure)` /
  `->hidden(bool|Closure)`. The closure is **server-evaluated** during render and
  receives a `Get` accessor (and the current operation). Hidden fields are not
  rendered and **not validated**.
- **FRM-09** **Operation-aware visibility** — `->visibleOn(string|array)` /
  `->hiddenOn(string|array)` where operation ∈ `create` | `edit` | `view`. The
  `Form` component exposes the current operation (it already knows `isEdit()`).
- **FRM-10** **Cross-field reactivity** — `->afterStateUpdated(Closure)` on a
  `->live()` field. Receives `($state, Get $get, Set $set)` and may mutate other
  fields (e.g. derive `slug` from `name`). Runs during the live re-render.
- **FRM-11** **State accessors** — a `Get` and `Set` value object over the form
  state, replacing raw `$formData` array access in callbacks
  (`optionsUsing`, `visible`, `afterStateUpdated`). `$get('field')` reads;
  `$set('field', value)` writes. (Stretch: typed reads `$get->int()`,
  `$get->bool()`, …, matching Filament.)
- **FRM-12** **Fluent validation helpers** compiling to Symfony constraints, so
  common rules read fluently and are IDE-discoverable instead of
  `rules([new Length(max: 5)])`: `->maxLength()`, `->minLength()`, `->length()`,
  `->min()`, `->max()`, `->same(field)`, `->regex()`. Extends the existing
  `TextField::email()/url()` pattern. `rules([...])` stays as the escape hatch.
- **FRM-13** **Presentation niceties** — `->placeholder(string)` (all text-like
  fields, not just select), `->autofocus()`, `->hiddenLabel()` (a11y-only label),
  `->inlineLabel()` (label beside input). Map to widget template flags.
- **FRM-14** **Dehydration control** — `->dehydrated(false)` (a field shown but
  not written to the model) and confirm the existing `normalize()` /
  `toFormValue()` cover Filament's `dehydrateStateUsing()` / `formatStateUsing()`
  (they do; document the mapping).

> Note: `FRM-07` (field-level authorization) is unchanged and orthogonal —
> authorization gates and `visible()` compose (a field hidden by either is hidden).

### 4.3 New field types — `FLD`

Cheap, native-input field types via the existing widget-template pattern:

- **FLD-01** **Radio** — `RadioField` (options as radio group; reuses Select's
  option/`optionsUsing` machinery).
- **FLD-02** **Toggle** — `ToggleField` (a styled boolean switch; sibling of
  `CheckboxField`, sharing its boolean normalize/constraints).
- **FLD-03** **Hidden** — `HiddenField` (renders `<input type=hidden>`; always
  dehydrated, no label).
- **FLD-04** **Color picker** — `ColorField` (`<input type=color>` + text value).
- **FLD-05** **Toggle buttons** — `ToggleButtonsField` (segmented single/multi
  choice).
- **FLD-06** **Tags input** — `TagsField` (string list).
- **FLD-07** **Key-value** — `KeyValueField` (string map).

Roadmap / out of scope for the first milestones, catalogued for completeness:

- **FLD-10** **Repeater** — array of repeated sub-schemas (needs the schema tree +
  array dehydration).
- **FLD-11** **Builder** — heterogeneous blocks.
- **FLD-12** **File upload** — needs an upload/storage abstraction (no Doctrine in
  core; storage via an interface).
- **FLD-13** **Rich editor / Markdown editor**, **Slider**, **Code editor**.

---

## 5. Architecture fit

- **Server-driven (PRD §5.2).** Visibility, `afterStateUpdated`, dependent
  options and validation all run on the server during the Live Component
  re-render that `->live()` already triggers. No new client framework. The only
  sanctioned local JS is collapsing a `Section` (SCH-09) as progressive
  enhancement.
- **No Doctrine in core (PRD §5.3).** `SCH`/`FRM`/`FLD` are presentation + form
  state only. File upload (`FLD-12`) introduces a storage **interface**, not a
  Doctrine dependency.
- **Downward deps only (PRD §5.1).** Layout components live alongside fields under
  `Atrium\Form\*`; no sideways dependency on Tables/Actions.
- **Inline path preserved (CLAUDE.md #6).** `fields([...])` is retained; the tree
  is opt-in via `components([...])` and layout components.
- **CSS shipped by the bundle.** Grid/section classes are part of the bundle's
  precompiled `assets/dist/atrium.css` (scan templates, `composer build-css`).
  Consumers need zero Tailwind config — same model as today.
- **BC discipline (PRD §9, CLAUDE.md #4).** `Schema` and `Field` are public API.
  Introducing `SchemaComponent`, `components()` and new fluent methods is
  **additive**; flag in `CHANGELOG.md`. Changing the return shape of an existing
  signature is not permitted — add, don't break.

---

## 6. DX contract (target API)

Mirrors PRD §9 style; this is the public surface these requirements must deliver.

```php
// A non-trivial form: sections + responsive grid + conditional + reactive fields.
final class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->description('Who the customer is.')
                ->columns(2)
                ->schema([
                    TextField::make('firstName')->required()->maxLength(50),
                    TextField::make('lastName')->required()->maxLength(50),
                    TextField::make('email')->required()->email()
                        ->columnSpanFull(),
                ]),

            Section::make('Billing')
                ->collapsible()
                ->columns(2)
                ->schema([
                    SelectField::make('country')->options(/* … */)->live(),
                    // Only ask for a state when the country needs one:
                    SelectField::make('state')
                        ->optionsUsing(fn (Get $get) => states($get('country')))
                        ->visible(fn (Get $get) => hasStates($get('country'))),

                    TextField::make('vatId')
                        ->label('VAT ID')
                        ->visibleOn('edit')        // not asked at creation
                        ->columnSpanFull(),
                ]),

            Grid::make(3)->schema([
                ToggleField::make('active')->default(true),
                ColorField::make('accent'),
                HiddenField::make('source')->default('admin'),
            ]),
        ]);
    }
}
```

```php
// Cross-field reactivity: derive slug from name without a client framework.
TextField::make('name')
    ->required()
    ->live()
    ->afterStateUpdated(fn (string $state, Get $get, Set $set) =>
        $set('slug', \Symfony\Component\String\u($state)->snake()->toString())
    );

TextField::make('slug')->required();
```

---

## 7. Rollout (milestones)

Each milestone is independently shippable, behind the per-phase definition of
done (tests green, PHPStan max, CS clean, `CHANGELOG.md` + docs updated). This
slots in as **Phase 2.5** (after the `make:atrium:resource` maker that closes
Phase 2), before Actions.

- **M1 — Schema tree + layout (`SCH-01..08`).** The keystone. `SchemaComponent`,
  `components()`, `Grid`/`Section`/`Fieldset`, `columnSpan`, recursive renderer +
  layout templates + CSS. *Acceptance:* a two-column section form renders and
  saves; nested Grid-in-Section works; `fields([...])` is byte-for-byte
  unchanged in behaviour.
- **M2 — Conditional visibility (`FRM-08, FRM-09, FRM-11 Get`).** Server-evaluated
  `visible/hidden/visibleOn/hiddenOn`; hidden fields skip validation. *Acceptance:*
  a field appears/disappears on a `->live()` change and per operation.
- **M3 — Reactivity + validation DX (`FRM-10, FRM-11 Set, FRM-12, FRM-13`).**
  `afterStateUpdated`, `Get`/`Set`, fluent validation helpers, placeholder/
  autofocus/hiddenLabel. *Acceptance:* name→slug demo works; `maxLength()` rejects.
- **M4 — Cheap field types (`FLD-01..05`).** Radio, Toggle, Hidden, Color,
  ToggleButtons. *Acceptance:* each renders, normalizes, validates, round-trips.
- **M5 — Stretch (`SCH-10` Tabs/Wizard, `FLD-06..07`, `FRM-14`).**
- **Future (`FLD-10..13`).** Repeater, Builder, File upload, Rich/Markdown — each
  its own PRD addendum when scheduled.

---

## 8. Acceptance criteria (this addendum)

- A resource form can declare Sections/Grids with responsive `columns()` and
  per-field `columnSpan`, rendered correctly in light **and** dark mode, with no
  consumer Tailwind configuration.
- Flat `fields([...])` resources (and the §9a inline path) continue to work
  unchanged — proven by the existing form tests staying green untouched.
- `visible()`/`visibleOn()` and `afterStateUpdated()` work via server re-render;
  hidden fields are neither rendered nor validated.
- New field types (`FLD-01..05`) pass the same widget + normalize + validate +
  persist coverage the v1 fields have.
- Public-API additions are flagged in `CHANGELOG.md`; no existing `FRM`/`§9`
  signature changes shape.

---

## 9. Open questions

- **`Get`/`Set` shape:** invokable objects (`$get('x')`) vs array-ish — go
  invokable to match Filament and allow typed reads later.
- **Collapsible JS:** Stimulus controller in the bundle vs a tiny inline toggle —
  prefer a small bundled Stimulus controller (still server-rendered markup).
- **Tabs vs Wizard priority** for M5 — Tabs is lower-risk; Wizard needs step
  validation semantics.
