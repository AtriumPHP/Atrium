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

- **SCH-01** A `Schema` is a **tree of components**. A component is a **field**
  (`Atrium\Form\Field\Field`), a **layout container** (`Atrium\Layout\*`), or a
  **content node** (`Atrium\Content\*` — see `CNT`). All implement a shared
  `Atrium\Layout\Component` contract exposing child components (leaves return
  none). Only fields carry form state; layout and content nodes are skipped by
  `Schema::getFields()`. **Layout lives at the
  top level (`Atrium\Layout`), not under `Atrium\Form`**, because the same
  grid/section machinery is intended to power dashboards, infolists and other
  views — not just forms. The generic renderer is view-agnostic: every node
  renders via its own `getTemplate()` (for a field, the wrapper; the field's
  input widget is `getWidgetTemplate()`).
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
- **SCH-11** **Flex** — `Flex::make()->schema([...])->from('md')`; a flexbox row
  (children side by side from the `from()` breakpoint up, stacked below) where
  widths are content-driven. Children opt out of growing with `->grow(false)`
  (`flex-none`) so siblings expand around them. Complements Grid (fixed columns)
  for toolbar-like rows of compact controls.

### 4.2 Field-level capabilities — `FRM` (continued)

- **FRM-08** **Conditional visibility — ✅ delivered.** `->visible(bool|Closure)` /
  `->hidden(bool|Closure)`. The closure is **server-evaluated** during render and
  receives a `Get` accessor. Hidden fields are not rendered, **not validated**
  and not persisted; a container left empty by hidden fields is dropped.
- **FRM-09** **Operation-aware visibility — ✅ delivered.** `->visibleOn(string|array)` /
  `->hiddenOn(string|array)` where operation ∈ `create` | `edit` (`view` arrives
  with view pages). The `Form` component exposes `operation()`.
- **FRM-10** **Cross-field reactivity — ✅ delivered.** `->afterStateUpdated(Closure)`
  on a field (auto-implies `->live()`). Receives `($state, Get $get, Set $set)`
  and may mutate other fields (e.g. derive `slug` from `name`). The Form diffs
  `formData` against the previous render in a `#[PreReRender]` pass — a sub-path
  model write (`formData[name]`) can't be caught by an `onUpdated` hook with
  dynamic field names, so the snapshot diff is the correct mechanism.
- **FRM-11** **State accessors** — `Get` and `Set` value objects over the form
  state, replacing raw `$formData` access in callbacks. **Both ✅ delivered**
  (`Atrium\Form\Get` invokable read; `Atrium\Form\Set` invokable write). (Stretch:
  typed reads `$get->int()`, `$get->bool()`, …, matching Filament.)
- **FRM-12** **Fluent validation helpers — ✅ delivered.** `->maxLength()`,
  `->minLength()`, `->length()`, `->regex()` (→ `Length`/`Regex`) and numeric
  `NumberField::min()/max()` (→ `GreaterThanOrEqual`/`LessThanOrEqual`).
  `->same(field)` / `->different(field)` are cross-field rules the form evaluates
  against the full submitted state (a Symfony constraint can't see a sibling).
  `rules([...])` stays the escape hatch.
- **FRM-13** **Presentation niceties — ✅ delivered.** `->placeholder(string)`
  (Text/Textarea/Number), `->autofocus()`, `->hiddenLabel()` (a11y-only label),
  `->inlineLabel()` (label rendered beside the input in a responsive column).
- **FRM-14** **Dehydration control — ✅ delivered.** `->dehydrated(false)` — a field
  shown and validated but not written to the model (the save hydrate loop skips
  it). The existing `normalize()` / `toFormValue()` already cover Filament's
  `dehydrateStateUsing()` / `formatStateUsing()`.

> **Container-level visibility — ✅ delivered.** `visible()`/`hidden()`/`visibleOn()`/
> `hiddenOn()` now also work on **layout containers** (`Section`/`Grid`/`Flex`/
> `Fieldset`): a hidden container drops its whole subtree. To keep `Atrium\Layout`
> independent of `Atrium\Form`, visibility moved to `Atrium\Layout\Concern\HasVisibility`
> over a generic `Atrium\Layout\StateAccessor` (which `Atrium\Form\Get` implements).

> Note: `FRM-07` (field-level authorization) is unchanged and orthogonal —
> authorization gates and `visible()` compose (a field hidden by either is hidden).

### 4.3 New field types — `FLD` — ✅ delivered (`FLD-01..05`)

Cheap, native-input field types via the existing widget-template pattern:

- **FLD-01** **Radio** — `RadioField` (options as a radio group; extends
  `SelectField` to reuse `options()`/`optionsUsing()`).
- **FLD-02** **Toggle** — `ToggleField` (a styled boolean switch; extends
  `CheckboxField`, sharing its boolean normalize/`IsTrue` constraint/inline label).
- **FLD-03** **Hidden** — `HiddenField` (`rendersInLayout() === false`: stays in
  the form state — validated/persisted — but draws no widget and takes no grid
  cell; set via `default()` or `afterStateUpdated()`).
- **FLD-04** **Color picker** — `ColorField` (`<input type=color>` + hex preview).
- **FLD-05** **Toggle buttons** — `ToggleButtonsField` (segmented single choice;
  extends `SelectField`). Multi-select is a later extension.
- **FLD-06** **Tags input — ✅ delivered.** `TagsField` (`list<string>`; an
  add/remove row editor — one input per tag — driven by generic `addRow`/
  `removeRow` Live Component actions. Zero-JS beyond the round-trip).
- **FLD-07** **Key-value — ✅ delivered.** `KeyValueField` (`array<string,string>`;
  add/remove rows of a key input + a value input, same generic row plumbing).
  Both opt in via the `RepeatableField` contract so the renderer stays generic.

Roadmap / out of scope for the first milestones, catalogued for completeness:

- **FLD-10** **Repeater** — array of repeated sub-schemas (needs the schema tree +
  array dehydration).
- **FLD-11** **Builder** — heterogeneous blocks.
- **FLD-12** **File upload** — needs an upload/storage abstraction (no Doctrine in
  core; storage via an interface).
- **FLD-13** **Rich editor / Markdown editor**, **Slider**, **Code editor**.

### 4.4 Content components — `CNT`

Static building blocks that insert arbitrary content into a schema — the
equivalent of Filament's "prime" components, named for what they do. They live in
`Atrium\Content\*`, implement `Atrium\Layout\Component`, and are leaves with **no
form state**: never hydrated, never validated, skipped by `getFields()`. They can
still take grid/flex placement (`columnSpan`, `grow`), which makes them ideal for
headings, instructions and callouts beside fields.

- **CNT-01** **Text** — `Text::make(string|\Stringable)` with `->color()`
  (semantic: gray/info/success/warning/danger/primary), `->size()`
  (sm/base/lg/xl), `->weight()` (normal/medium/semibold/bold), `->badge()` (pill
  style) and `->html()` (render trusted markup). Light + dark mode.
- **CNT-02** **UnorderedList** — `UnorderedList::make(list<string>)`; a bulleted
  list for checklists/instructions.
- **CNT-03** **Image** — `Image::make(url, alt)` with `->imageWidth()/imageHeight()/imageSize()`
  and `->alignStart()/alignCenter()/alignEnd()`.
- **CNT-04** (Deferred) **Icon** — needs an icon-rendering system (nav icons are
  currently opaque string identifiers); revisit when the theme/icon layer lands.
- **CNT-05** (Future) closure-driven content (`$get`-aware) once `Get`/`Set`
  (`FRM-11`) exist.

**Status:** `CNT-01..03` ✅ delivered (`Atrium\Content\{Text,UnorderedList,Image}`,
templates under `components/content/*`, typography/colour utilities safelisted).

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
  Introducing `Atrium\Layout\Component`, `components()` and new fluent methods is
  **additive**; flag in `CHANGELOG.md`. The one breaking rename in M1 —
  `Field::getTemplate()` (widget) → `getWidgetTemplate()`, with `getTemplate()`
  now returning the field's layout wrapper — is flagged below and was made before
  any external release.

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

- **M1 — Schema tree + layout (`SCH-01..08`, `SCH-11`) — ✅ delivered.** The
  keystone. `Atrium\Layout\Component`, `components()`,
  `Grid`/`Flex`/`Section`/`Fieldset`, `columnSpan`/`columnSpanFull` and
  `grow()`/`from()`, the view-agnostic recursive renderer + layout templates +
  safelisted grid/flex CSS. Layout was promoted to the top-level `Atrium\Layout`
  namespace for reuse beyond forms. *Verified:* two-column sections with
  full-width spans and a Flex row render and save (a reactive dependent select
  works inside a grid); nested layout works; flat `fields([...])` is unchanged.
  *Deferred to a later milestone:* responsive `columnSpan` arrays,
  `columnStart`/`columnOrder`, Section `aside`/`icon`, Tabs/Wizard (`SCH-10`).
- **M2 — Conditional visibility (`FRM-08, FRM-09, `Get` of `FRM-11`) — ✅ delivered.**
  Server-evaluated `visible/hidden/visibleOn/hiddenOn` via the invokable
  `Atrium\Form\Get`; the Form filters hidden fields from the render tree and skips
  them in validation/hydration; empty containers are dropped. Also covered: the
  three content components from §4.4 (`CNT-01..03`). *Verified:* a field
  appears/disappears on a `->live()` change (subcategory follows category) and per
  operation (`visibleOn('edit')`); hidden fields are not validated.
- **M3 — Reactivity + validation DX (`FRM-10, FRM-11 Set, FRM-12, FRM-13`) — ✅ delivered.**
  `afterStateUpdated` (via a `#[PreReRender]` snapshot diff), the `Set` accessor,
  fluent validation helpers (`maxLength`/`minLength`/`length`/`regex`, numeric
  `min`/`max`), and `placeholder`/`autofocus`/`hiddenLabel`. *Verified:* name→SKU
  derivation works live in the browser; helpers compile to the right constraints.
  *Deferred:* `same()` (contextual validator), `inlineLabel()`.
- **M4 — Cheap field types (`FLD-01..05`) — ✅ delivered.** Radio, Toggle, Hidden,
  Color, ToggleButtons — reusing `SelectField`/`CheckboxField` where possible, with
  native-input widgets. `Field::rendersInLayout()` lets `HiddenField` stay in the
  state while drawing nothing. *Verified:* each renders/normalizes/round-trips;
  hidden is filtered from the layout but kept in `getFields()`.
- **M5 — delivered (bar Tabs/Wizard).** ✅ `FLD-06` Tags, `FLD-07` Key-value
  (now full add/remove row editors), `FRM-14` `dehydrated(false)`,
  **container-level visibility**, the `same()`/`different()` cross-field
  validators, and `inlineLabel`. *Remaining:* `SCH-10` Tabs/Wizard (its own pass).
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
