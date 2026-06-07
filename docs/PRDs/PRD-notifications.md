# PRD — Notifications (Phase 6, part 1)

> **Status:** design approved 2026-06-07. Scope: transient toast notifications
> (`NTF-01`, `NTF-02`). Persisted/database notifications (`NTF-03`) are **deferred**
> to their own cycle. The plugin system (the other half of PRD §10 Phase 6) is a
> separate brainstorm/spec/plan cycle.

## 1. Summary

A server-driven **notification** subsystem for Atrium: a fluent `Notification`
builder that any action, component, controller or service can raise, delivered
through two channels — a **live channel** (appears with no page reload, from a
Live Component) and a **Symfony flash bridge** (survives a redirect) — and
rendered as stacking, auto-dismissing **toasts** in the panel layout. It replaces
today's single hardcoded "saved successfully" notice in the form template.

This is the first Atrium subsystem to **ship its own JavaScript**: one small
Stimulus controller for toast timing/animation, auto-registered through the
standard Symfony UX/StimulusBundle mechanism so integrating apps still write
**zero JavaScript**.

## 2. Goals / non-goals

**Goals**

- A reusable, fluent `Notification` value object in the established builder style.
- Raise a notification from a Live Component **without a redirect** (live channel).
- Raise a notification that **survives a redirect** (flash bridge) — e.g.
  redirect-after-save shows the toast on the destination page.
- Toasts: title, optional body, semantic **status** (success/danger/warning/info)
  driving a default icon + color, icon/color overrides, configurable **duration**,
  a **persistent** (no auto-dismiss) mode, manual close, and stacking.
- **In-toast actions**: a link button (URL) and a "live" button (emits a named
  Live Component event).
- Wire the existing save/delete flows to raise real notifications.
- No Doctrine in core; no sideways package deps; server-driven; documented;
  dogfooded in the playground.

**Non-goals (this cycle)**

- `NTF-03` persisted/database notifications and the topbar unread indicator
  (separate cycle — needs a storage seam).
- Closure-carrying toast actions (`Atrium\Action\Action` reuse) — closures don't
  serialize across the flash bag or a signed live-event payload.
- Per-user notification preferences, channels other than the panel UI
  (email/SMS/Slack), notification grouping/threading.

## 3. Architectural constraints (from CLAUDE.md)

- **No Doctrine types** in the `Atrium\Notification` namespace. Transient toasts
  need no storage, so this is naturally satisfied.
- **Dependencies point downward only.** `Atrium\Notification` may depend on
  foundation/contracts (the URL scheme-guard, UX Icons, the shared color
  vocabulary) but not sideways onto Tables/Forms/etc. The Form/Action *wiring*
  lives in those subsystems and depends **onto** Notification, never the reverse.
- **Server-driven.** Reactivity is Live Components + a DOM morph. The shipped
  Stimulus controller handles only client-local concerns (a dismiss timer and CSS
  transitions); all notification *content and state* is server-authored.
- **The Resource API is a stable contract.** The new public surface
  (`Notification`, `NotificationAction`, `NotificationStatus`,
  `InteractsWithNotifications`, `Notifier`) is additive. Replacing the inline form
  success notice with a toast is a **minor pre-1.0 BC** flagged in the CHANGELOG.

## 4. Public API surface

All new types live under `Atrium\Notification`.

### 4.1 `Notification` (fluent builder)

```php
use Atrium\Notification\Notification;
use Atrium\Notification\NotificationAction;

Notification::make()                 // optional explicit id; auto-generated otherwise
    ->title('Article published')     // required
    ->body('“Hello world” is now live.')   // optional
    ->success()                      // status shortcut: success()/danger()/warning()/info()
    ->icon('lucide:rocket')          // optional; defaults from status
    ->color('green')                 // optional; defaults from status
    ->duration(5000)                 // ms before auto-dismiss (default DURATION_DEFAULT)
    ->persistent()                   // no auto-dismiss; stays until closed (duration = null)
    ->actions([
        NotificationAction::make('View')->url($url),
        NotificationAction::make('Undo')->emit('article:undo', ['id' => 42]),
    ]);
```

| Method | Signature | Description |
| --- | --- | --- |
| `make` | `static make(?string $id = null): self` | Start a notification; `$id` auto-generated (`bin2hex(random_bytes(8))`) if null. The id keys the toast in the DOM and the dismiss action. |
| `title` | `title(string $title): self` | The bold heading (required before send). |
| `body` | `body(?string $body): self` | Optional supporting line. |
| `success` / `danger` / `warning` / `info` | `(): self` | Set the status (default icon + color). |
| `status` | `status(NotificationStatus $status): self` | Set the status directly. |
| `icon` | `icon(?string $icon): self` | Override the status's default icon (UX Icons name). |
| `color` | `color(?string $color): self` | Override the status's default semantic color. |
| `duration` | `duration(int $milliseconds): self` | Auto-dismiss delay; clears `persistent`. |
| `persistent` | `persistent(bool $persistent = true): self` | Disable auto-dismiss (`duration` ignored). |
| `actions` | `actions(array $actions): self` | `list<NotificationAction>` rendered as buttons. |
| `toArray` | `toArray(): array` | Pure-scalar payload for both channels. |
| `fromArray` | `static fromArray(array $data): self` | Rebuild from `toArray()` (rejects malformed input). |

`DURATION_DEFAULT` is a public class constant (e.g. `5000`).

### 4.2 `NotificationStatus` (enum)

`Success | Danger | Warning | Info`, plus the no-status default. Each case maps to:

| Status | Default icon (Lucide) | Default color |
| --- | --- | --- |
| Success | `circle-check` | `green` |
| Danger | `circle-x` | `red` |
| Warning | `triangle-alert` | `amber` |
| Info | `info` | `blue` |
| *(none)* | `bell` | `gray` |

Methods: `defaultIcon(): string`, `defaultColor(): string`. Colors resolve through
the existing shared semantic-color vocabulary (`ResolvesColor`).

### 4.3 `NotificationAction` (fluent, serializable)

A deliberately lightweight, **serializable** action — *not* the closure-based
`Atrium\Action\Action`.

| Method | Signature | Description |
| --- | --- | --- |
| `make` | `static make(string $label): self` | Start an action with its button label. |
| `icon` | `icon(?string $icon): self` | Optional leading icon. |
| `color` | `color(?string $color): self` | Optional semantic color (defaults to neutral). |
| `url` | `url(string $url): self` | Make it a **link** (scheme-guarded `href`). Survives the flash bridge. |
| `emit` | `emit(string $event, array $payload = []): self` | Make it a **live** action: clicking emits a named Live event the app can listen for. Live-channel only. |
| `closeOnClick` | `closeOnClick(bool $close = true): self` | Dismiss the toast after the action fires (default true). |
| `toArray` / `fromArray` | — | Scalar serialization. |

A `NotificationAction` is exactly one of link or emit; constructing it as neither,
or both, is a programming error (assertion at send time).

### 4.4 `InteractsWithNotifications` (trait, live channel)

A trait for **Live Components**. Provides:

```php
protected function notify(Notification $notification): void;
```

which emits the `atrium:notification` live event carrying `$notification->toArray()`.
Convenience shortcuts `notifySuccess(string $title, ?string $body = null)` etc. are
included to keep call sites terse.

### 4.5 `Notifier` (service, flash channel)

An injectable service (no global state / no facade — consistent with the codebase):

```php
public function send(Notification $notification): void;   // push to flash bag
```

Writes `$notification->toArray()` into the session flash bag under the key
`atrium.notifications` (a list). Used by plain controllers/services and **before a
redirect**. Resolving the session goes through `RequestStack`, so `Notifier` is a
no-op when no session is available (CLI/sub-requests) rather than throwing.

## 5. Render host + Stimulus controller

### 5.1 `Atrium:Notifications` Live Component

- Mounted **once** in `templates/admin/layout.html.twig` (fixed top-right region).
- On `mount()`: **drains the flash bag** (`atrium.notifications`) into its initial
  stack, so flashed notifications appear on the destination page after a redirect.
- `#[LiveListener('atrium:notification')]`: appends the live-event payload to the
  stack and re-renders (the toast morphs in).
- The active stack is a **non-writable (signed) LiveProp** `array` — clients cannot
  forge or inject notifications; they are only ever appended server-side via the
  listener or the flash drain.
- `#[LiveAction] dismiss(string $id)`: removes the toast with that id from the
  stack. Used by both the auto-dismiss timer and the manual close button. A forged
  id simply matches nothing.
- `#[LiveAction] runAction(string $notificationId, int $actionIndex)`: for a live
  (`emit`) toast action — looks up the action **in the server-held stack** and
  re-emits its named event with its payload. Because the host only knows actions it
  actually rendered (signed state), a forged event name cannot be injected.

### 5.2 `assets/controllers/notifications_controller.js`

The bundle's first shipped Stimulus controller. Responsibilities are **client-local
only**:

- Animate each toast in on insert and out on removal (CSS transitions).
- Start a per-toast auto-dismiss timer from its `duration` data attribute (skipped
  when persistent); on expiry, trigger the host's `dismiss(id)` live action.
- The **×** button triggers the same `dismiss(id)`.
- Pause the timer on hover (so a toast isn't lost mid-read), resume on leave.

Shipped through the standard Symfony UX/StimulusBundle bundle convention
(`controllers.json` + the bundle's `assets/` exposed to AssetMapper) so the
consumer's existing Stimulus runtime — already required for Live Components —
auto-registers it. **No consumer JS, no build step.**

> **Implementation risk to resolve in the plan:** the exact wiring by which an
> AssetMapper-based bundle ships a Stimulus controller that the host app
> auto-discovers (controllers.json location, `composer.json` `extra.symfony`
> entries, AssetMapper path registration). This is the only genuine unknown; the
> plan must pin it down (and the functional test must prove the controller asset is
> exposed) before NTF-M2 is "done".

## 6. Data flow

**Live (no redirect):**
`Component action → $this->notify($n) → emit atrium:notification → Atrium:Notifications listener appends → re-render → Stimulus animates in + arms timer.`

**Flash (across redirect):**
`Controller/service → $notifier->send($n) → flash bag → redirect → next page → host mount() drains flash → renders toasts.`

**Dismiss:**
`timer expiry / × click → Stimulus → host dismiss(id) → stack drops it → morph out.`

**Live toast action:**
`button → host runAction(id, index) → re-emit named event + payload → an app Live Component listens and responds.`

## 7. Wiring into existing flows

- **`Form::save()`** raises a real **success** notification instead of the hardcoded
  inline `formStatus` notice: a **live** toast when the form stays on the page, a
  **flashed** toast when a page hook redirects (so it shows on the list). The inline
  success panel in `templates/components/form.html.twig` is removed. *(Minor
  pre-1.0 BC — flagged in CHANGELOG.)*
- **Delete / bulk-delete** built-ins raise a success toast.
- **Custom actions:** the `Atrium\Action\Action` builder gains an optional
  `successNotification(string $title, ?string $body = null)`; when set, the
  component that runs the action sends it after the handler succeeds. (Closures in
  actions can also call `$this->notify(...)` directly via the host component's
  trait.) Keep this thin — the declarative message covers the common case.

## 8. Security & error handling

- Link-action URLs pass through the existing `javascript:` / `data:` scheme guard
  before reaching an `href`.
- Live (`emit`) actions are safe by construction: the host only emits events for
  actions held in its **signed** LiveProp stack, so a crafted `runAction` call
  cannot fire an arbitrary app event.
- The notification stack is a non-writable LiveProp; clients cannot inject or
  mutate notifications. `dismiss(id)` with a forged id is a harmless no-op.
- The flash payload is **server-authored** (never client input), so rendering it is
  safe; body/title are escaped by Twig (no raw-HTML mode in this cycle).
- Live actions apply to **live-channel** notifications only. A flash-bridged
  notification keeps **link actions only**; emit-actions are dropped on
  serialization to the flash bag (documented).
- Unknown status/icon degrades to the neutral default, matching the panel's
  existing icon fallback.
- `Notifier` with no active session is a no-op (no exception).

## 9. Testing strategy

**Unit**

- `Notification` builder: status shortcuts set icon+color; overrides win;
  `duration`/`persistent` are mutually exclusive; `toArray`/`fromArray` round-trip;
  `fromArray` rejects malformed input.
- `NotificationStatus`: `defaultIcon`/`defaultColor` per case.
- `NotificationAction`: link-vs-emit exclusivity; `toArray`/`fromArray`; emit-action
  dropped when serialized for flash.

**Functional (kernel / `InteractsWithLiveComponents`)**

- `Atrium:Notifications` drains the flash bag on `mount()` and renders the toasts.
- A component using `InteractsWithNotifications` emits `atrium:notification`; the
  host appends and renders it.
- `dismiss(id)` removes a toast from the stack.
- `runAction` re-emits the held action's event; a forged index/event does nothing.
- `Form::save()` raises a success notification (live and, on redirect, flashed).
- The shipped Stimulus controller asset is exposed via AssetMapper (proves the
  bundle-JS wiring).

**Browser-verify (playground)**

- A real toast animates in, auto-dismisses, stacks, pauses on hover, and an `Undo`
  emit triggers an app listener.

## 10. Documentation

- `docs/integration-guide/notifications/overview.md` — the canonical template:
  summary, when-to-use, copy-pasteable example (live + flash), an API-reference
  table for `Notification`/`NotificationAction`/`NotificationStatus`/the trait/the
  service, the two channels, the live-vs-flash action rule, and the
  zero-consumer-JS asset note.
- `CHANGELOG.md` `[Unreleased]`: the new subsystem + the minor form-notice BC.
- `docs/PRDs/PRD.md` §10 Phase 6: mark the notifications half delivered, plugins
  pending.

## 11. Milestones

1. **NTF-M1 — core value objects.** `Notification`, `NotificationStatus`,
   `NotificationAction`, serialization; unit tests; CHANGELOG stub. No UI yet.
2. **NTF-M2 — render host + live channel.** `Atrium:Notifications` component +
   template, the Stimulus controller + bundle-JS wiring (resolve the risk above),
   layout mount, `InteractsWithNotifications` trait, `dismiss`, `runAction`;
   functional tests incl. the asset-exposure check.
3. **NTF-M3 — flash bridge + flow wiring.** `Notifier` service + flash drain on
   mount; wire `Form::save()` (replace inline notice) and delete/bulk-delete;
   `Action::successNotification()`; functional tests.
4. **NTF-M4 — docs, dogfood, polish.** Integration guide; playground demo
   (a custom action raising a toast + an `Undo` emit); browser-verify; CHANGELOG;
   separate code-review agent.

Each milestone ends green on `composer test && composer phpstan && composer cs`.

## 12. Acceptance criteria

- Any Live Component can raise a toast that appears with no reload; any
  controller/service can flash a toast that appears after a redirect.
- Toasts stack, auto-dismiss (or stay when persistent), pause on hover, and close
  manually — with the consumer writing no JavaScript.
- A toast can carry a link action (any channel) and a live `emit` action (live
  channel), the latter safe against forgery.
- `Form` save and delete surface real notifications; the old inline notice is gone.
- All gates green; integration guide + CHANGELOG updated; playground dogfoods it.
