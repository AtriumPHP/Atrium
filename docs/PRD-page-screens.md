# Atrium — Page screens: headings & header actions (PRD addendum)

> **Status:** Proposed · **Type:** Addendum to [`docs/PRD.md`](./PRD.md)
> **Extends:** Pages (`RES-06`, `LAY-03`), Actions (`ACT`), §9 DX contract
> **Supersedes:** the just-shipped `TableConfiguration::headerActions()` (unreleased
> default API) — header actions move to the Page / resource. Other table behaviour
> is unchanged.

This document turns `Atrium\Page\Page` from a single redirect hook into a real
**screen descriptor**: per-screen **headings** (title / heading / subheading) and
**header actions** (including server-driven ones on the create/edit screens). It
introduces a new **`PAG`** requirement namespace.

---

## 1. Motivation

A `Page` today carries exactly one method — `getRedirectUrl()`. The
`ListPage`/`CreatePage`/`EditPage` subclasses are empty, and `ListPage` is in fact
**never resolved** (the controller renders the list directly). Headings are
hardcoded in the controller (`"New {Label}"`, `"Edit {Label}"`,
`resource.label`); the create/edit screens' only header control is a hardcoded
"back to list" link; and the list screen's header actions live on the *table*
(`table()->headerActions()`), rendered server-driven inside the DataTable.

That is a thin justification for a whole Page hierarchy. This addendum makes Pages
earn their keep by owning each screen's **presentation surface**.

**Why presentation only.** Pages are instantiated with `new $class()`
(`AdminResource::resolvePage()`) — they are **not** DI services. So data /
lifecycle hooks (form mutation, save side-effects, authorization) must stay on the
resource, where injected services are available. Pages get headings and header
actions (which need no DI); they do not get data hooks.

## 2. Goals

- `Page` owns its screen's **title / heading / subheading**, with sensible
  computed defaults per action, overridable per resource.
- `Page` owns its screen's **header actions**, including **server-driven** ones on
  the create/edit screens (e.g. Delete / Duplicate on edit), hosted and dispatched
  by the screen's existing Live Component.
- Keep the **inline path** cheap: a small resource sets header actions in one
  method on the resource, without writing a Page class.
- Unify header actions onto the Page model (one conceptual home), retiring
  `table()->headerActions()`.
- Change **no data/lifecycle hook** and introduce **no client JS**.

## 3. Non-goals (v1 of this addendum)

- **Moving data/lifecycle hooks to Pages.** Pages aren't services; form-mutation,
  save side-effects and authorization stay on the resource.
- **Custom, non-CRUD pages** (a "Settings" / "Reports" screen with its own route
  and view). A separate, larger effort that overlaps the dashboard/widget routing.
- **Record (row) actions and bulk actions** — unchanged; they remain on the table.
- **Per-page form/table *config*** (a page choosing a different schema). Out of
  scope; the resource still owns `form()`/`table()`.

## 4. Design

### 4.1 `Page` becomes a screen descriptor

```php
abstract class Page
{
    public function getTitle(PageContext $context): string;        // browser <title>; defaults to the heading
    public function getHeading(PageContext $context): string;      // the <h1>
    public function getSubheading(PageContext $context): ?string;  // optional sub-line; null by default

    /** @return list<ActionContract>|null  null = defer to the resource's inline header actions */
    public function getHeaderActions(PageContext $context): ?array;

    public function getRedirectUrl(PageContext $context): ?string; // unchanged
}
```

Defaults per subclass (computed from `PageContext` labels — see §4.2):

| Page | `getHeading()` | `getHeaderActions()` |
| --- | --- | --- |
| `ListPage` | the plural label | `null` (→ resource; resource default `[CreateAction::make()]`) |
| `CreatePage` | `"New {singular}"` | `null` (→ resource; default `[]`) |
| `EditPage` | `"Edit {singular}"` | `null` (→ resource; default `[]`) |

### 4.2 `PageContext` carries labels

`PageContext` gains `singularLabel` and `pluralLabel` (set by the controller /
component, which already have the resource), so a Page computes default headings
without a reference to the full resource. It keeps `resourceSlug`, `pathPrefix`,
`entityId`, `indexUrl()/createUrl()/editUrl()`.

### 4.3 Inline shortcut + precedence (keeps the inline path cheap)

The resource exposes an **inline** header-action method; the default Pages defer
to it via a **null sentinel**:

```php
// AdminResource — the inline path (no Page class needed):
/** @return list<ActionContract> */
public function getHeaderActions(string $action, PageContext $context): array
{
    return 'index' === $action ? [CreateAction::make()] : [];
}
```

The effective header actions for a screen are resolved as:

```
$page?->getHeaderActions($ctx) ?? $resource->getHeaderActions($action, $ctx)
```

- **Default Pages return `null`** → the resource's inline method wins (so a small
  resource just overrides `AdminResource::getHeaderActions()` — one method, no Page
  class).
- A **dedicated Page** that overrides `getHeaderActions()` to return an array
  **takes precedence** (the delegated path).

This mirrors `table()`/`form()`: inline on the resource for small cases, a
dedicated class when you want one. The cost — header actions can live in two
places — is bounded by the clear precedence rule and documented.

### 4.4 Headings flow (static, controller-driven)

The controller resolves the Page for **all three** screens (finally using
`ListPage`) and passes `getHeading()/getSubheading()/getTitle()` into the layout's
sticky `<header>` (h1 + an optional subheading line). No Live Component involved —
headings are static.

### 4.5 Header-actions flow (live, component-hosted)

The Page *declares* header actions; the screen's **existing Live Component renders
and dispatches** them, reusing the generic
`Atrium\Action\Concern\InteractsWithActions` (the `requestAction` / `confirmAction`
/ `cancelAction` machinery + confirm modal; the host implements `findAction()`,
`canExecuteAction()`, `executeAction()`):

- **List screen → DataTable.** It already hosts header actions; change the *source*
  from `table()->headerActions()` to the resolved index page / resource
  (§4.3). Server-driven list header actions keep working unchanged.
- **Create / Edit screen → Form component.** The Form component gains
  `InteractsWithActions`, resolves its page (create when `entityId` is null, else
  edit) and the effective header actions, renders a header-actions bar at the top
  of its own DOM, and dispatches:
  - **Edit:** the action runs against the **loaded entity** (the record being
    edited) — so `DeleteAction` / a `Duplicate` handler work. After a successful
    delete the component redirects to the list.
  - **Create:** there is no record; create header actions are typically links, and
    a server-driven one runs record-less (consistent with table header actions,
    which are already record-less).

This matches the **existing** list-screen split — heading in the sticky bar,
actions inside the Live Component — now applied to all three screens.

### 4.6 BC change

`TableConfiguration::headerActions()` (and its `[CreateAction::make()]` default) is
**removed**; the default New button becomes `ListPage` / the resource default.
Unreleased, so internal-only migration (playground, fixtures, docs).

## 5. Requirements — `PAG`

- **PAG-01** `Page::getHeading()/getTitle()/getSubheading()` with per-subclass
  computed defaults; rendered in the layout header for all three screens.
- **PAG-02** `PageContext` carries `singularLabel`/`pluralLabel`.
- **PAG-03** `Page::getHeaderActions(): ?array` + `AdminResource::getHeaderActions(string
  $action, PageContext): array` inline shortcut, resolved by the null-sentinel
  precedence rule.
- **PAG-04** `ListPage` is resolved for the index screen (no longer dead); the
  DataTable sources its header actions from the page/resource.
- **PAG-05** The Form component hosts and dispatches the create/edit screen's
  header actions via `InteractsWithActions`, server-driven, against the loaded
  record on edit (post-delete redirect to the list).
- **PAG-06** `TableConfiguration::headerActions()` removed; default New button
  relocated. **BC.**

## 6. Developer-facing API contract (DX spec)

Inline (small resource — one method, no Page class):

```php
use Atrium\Page\PageContext;
use Atrium\Table\Action\CreateAction;

public function getHeaderActions(string $action, PageContext $context): array
{
    return match ($action) {
        'index' => [CreateAction::make(), ImportAction::make()],
        default => [],
    };
}
```

Delegated (a dedicated Page — headings + a server-driven edit action):

```php
namespace App\Admin\Pages;

use Atrium\Action\Action;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Page\EditPage;
use Atrium\Page\PageContext;
use Atrium\Table\Action\DeleteAction;

final class EditProduct extends EditPage
{
    public function getSubheading(PageContext $context): ?string
    {
        return 'Editing product #'.$context->entityId;
    }

    public function getHeaderActions(PageContext $context): ?array
    {
        return [
            Action::make('duplicate')->label('Duplicate')->icon('plus')
                ->action(fn (object $record, DataWriterInterface $writer) => /* … */),
            DeleteAction::make(), // server-driven; runs against the edited record
        ];
    }
}
```

## 7. Risks & mitigations

| Risk | Severity | Mitigation |
| --- | --- | --- |
| Form component grows (hosting + dispatching actions). | Medium | Reuse `InteractsWithActions` (verified generic — three abstract hooks); the Form already loads the record. Cover with functional tests. |
| Removing `table()->headerActions()` is a BC break. | Medium | Unreleased; migrate playground/fixtures/docs in the same change; call it out in `CHANGELOG`. |
| Header actions can live in two places (resource inline vs Page). | Low | One precedence rule (`page ?? resource`), documented; default pages return null. |
| Create-screen server-driven header action with no record. | Low | Record-less dispatch (consistent with table header actions); document that record-bound header actions belong on the edit screen. |
| Subheading/heading defaults need labels not in `PageContext`. | Low | Add `singularLabel`/`pluralLabel` to `PageContext`. |

## 8. Testing strategy

- **Unit:** `Page` heading/title/subheading defaults per subclass (from a
  `PageContext`); the null-sentinel precedence resolution.
- **Functional (kernel boot):** list/create/edit headings render; DataTable shows
  the resolved header actions; the Form component renders create/edit header
  actions and **dispatches a server-driven edit action** (e.g. a toggle/delete on
  the loaded record), with the confirm flow; post-delete redirect.
- **Browser (playground):** an edit screen with a server-driven header action
  (Delete or Duplicate) — no console errors, the action runs and redirects.

## 9. Documentation

Update `docs/integration-guide/pages/overview.md` (headings, header actions, the
inline shortcut vs dedicated Page, precedence), and
`docs/integration-guide/tables/actions.md` + `actions/overview.md` (header actions
move from the table to the page/resource). `CHANGELOG` + a note in the main PRD's
Pages section.

## 10. Implementation milestones

1. **Headings** — `Page` heading/title/subheading + `PageContext` labels +
   controller resolves all three pages + layout renders them. Ships value alone.
2. **Header actions** — `Page::getHeaderActions` + resource inline shortcut +
   precedence; DataTable source migration; Form-component hosting/dispatch
   (server-driven, post-delete redirect); remove `table()->headerActions()`.
3. **Playground + docs + browser-verify.**

## 11. Acceptance criteria

- Each screen's heading/subheading comes from its Page (defaults computed; a custom
  Page overrides them).
- A small resource adds a list header button by overriding one resource method; a
  dedicated Page can take over a screen's header actions.
- The edit screen runs a **server-driven** header action against the edited record
  (with confirm + post-delete redirect); the list "New" button still works.
- `table()->headerActions()` is gone; no data/lifecycle hook moved to a Page.
- `composer test && composer phpstan && composer cs` green; playground verified;
  docs + CHANGELOG updated.
