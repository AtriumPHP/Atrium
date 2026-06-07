# Notifications

> Transient toast messages surfaced in the panel shell — raise them from a Live
> Component for an in-place toast (no reload) or from a controller/service for a
> toast that survives a redirect.

## When to use

Reach for a notification whenever you need to give the user immediate feedback
about an operation: a record saved, a job queued, an error encountered. Atrium
ships **two delivery channels**:

- **Live channel** — the toast appears instantly, inside the current page, with
  no reload. Use it from any Live Component (a Form, a DataTable action, a
  custom component).
- **Flash channel** — the toast is queued in the Symfony flash bag and appears
  on the next rendered page. Use it from a controller, a service, or anywhere
  outside a Live Component, especially after a redirect.

The built-in Form save and delete actions raise success toasts automatically;
you only write notification code for custom actions or business operations.

## Live channel — from a Live Component

Add the `InteractsWithNotifications` trait to any Live Component. The component
must also `use ComponentToolsTrait` (the trait calls its `emit()` internally).

```php
use Atrium\Notification\Notification;
use Atrium\Notification\NotificationAction;
use Atrium\Twig\Components\Concern\InteractsWithNotifications;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('atrium:my_component')]
final class MyComponent
{
    use ComponentToolsTrait;          // required — InteractsWithNotifications calls emit()
    use DefaultActionTrait;
    use InteractsWithNotifications;

    public function save(): void
    {
        // ... persist ...

        // Convenience shortcut:
        $this->notifySuccess('Saved', 'The record has been updated.');

        // Or build a richer notification:
        $this->notify(
            Notification::make()
                ->title('Published')
                ->body('The article is now live.')
                ->success()
                ->actions([
                    NotificationAction::make('View')->url('/articles/my-slug'),
                ])
        );
    }
}
```

The shortcut methods `notifySuccess()` and `notifyDanger()` cover the two most
common cases without constructing the builder.

## Flash channel — from a controller or service

Inject `Atrium\Notification\Notifier` and call `send()`. The notification
survives the redirect and is drained on the next page render.

```php
use Atrium\Notification\Notification;
use Atrium\Notification\Notifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

final class PublishController extends AbstractController
{
    public function __construct(private readonly Notifier $notifier) {}

    #[Route('/articles/{id}/publish', methods: ['POST'])]
    public function publish(int $id): Response
    {
        // ... publish the article ...

        $this->notifier->send(
            Notification::make()
                ->title('Article published')
                ->body('The article is now live.')
                ->success()
        );

        return $this->redirectToRoute('app_articles_index');
    }
}
```

If no session is available (CLI context, sub-request) `send()` is a silent
no-op — it never throws.

## Statuses, icons and colours

A status sets the toast's default icon and semantic colour. Override either
independently on the notification.

| Status | Shortcut | Default icon | Default colour |
| --- | --- | --- | --- |
| `NotificationStatus::Success` | `->success()` | `circle-check` | `success` |
| `NotificationStatus::Danger` | `->danger()` | `circle-x` | `danger` |
| `NotificationStatus::Warning` | `->warning()` | `triangle-alert` | `warning` |
| `NotificationStatus::Info` | `->info()` | `info` | `info` |
| `NotificationStatus::Neutral` | *(default)* | `bell` | `gray` |

Override the icon or colour at any time; the status still expresses semantic
meaning even if the visuals are customised:

```php
Notification::make()
    ->title('Queued')
    ->info()
    ->icon('clock')         // override the default 'info' icon
    ->color('primary');     // override the default 'info' colour
```

## In-toast actions

A notification can carry one or more action buttons. There are exactly two
kinds:

**Link action** — renders an `<a href>`, works on both channels. URLs are
scheme-guarded: `http(s)`, `mailto:`, `tel:`, relative and root-relative paths
are accepted; anything else (e.g. `javascript:`) is silently dropped.

```php
NotificationAction::make('View article')->url('/articles/my-slug');
```

**Emit action** — clicking emits a named Live Component event on the page. **Live
channel only.** Emit actions are automatically stripped from flash-channel
payloads (an event emitted after a redirect reaches no live listener).

```php
NotificationAction::make('Undo')->emit('article:undo', ['id' => $article->id]);
```

Combine them in a single notification — the channel will keep whichever kind is
appropriate:

```php
Notification::make()
    ->title('Draft saved')
    ->success()
    ->actions([
        NotificationAction::make('View')->url('/articles/'.$article->slug),
        NotificationAction::make('Publish now')->emit('article:publish', ['id' => $article->id]),
    ]);
```

### Controlling dismiss-on-click

By default a click on an action button closes the toast. Pass `false` to keep it
open:

```php
NotificationAction::make('Retry')->emit('job:retry')->closeOnClick(false);
```

## Duration, persistence and dismiss

By default every toast auto-dismisses after **5 000 ms**
(`Notification::DURATION_DEFAULT`). Override with `->duration()` or disable
auto-dismiss entirely with `->persistent()`:

```php
// Dismiss after 10 seconds.
Notification::make()->title('Long running task started')->info()->duration(10_000);

// Never auto-dismiss — the user must close manually.
Notification::make()->title('Action required')->warning()->persistent();
```

Call `->persistent(false)` to restore the default 5 000 ms timeout after setting
persistent.

## Built-in wiring

### Form saves and delete actions

The built-in form **save** (create and edit) and the table/header **delete**
actions raise a success toast automatically. No code is required on your resource.

### `Action::successNotification()` — custom actions

Call `successNotification()` on any server action to raise a toast when the
handler completes without throwing:

```php
use Atrium\Action\Action;

Action::make('archive')
    ->label('Archive')
    ->icon('archive')
    ->successNotification('Archived', 'The record has been archived.')
    ->action(function (object $record, DataWriterInterface $writer): void {
        $record->archivedAt = new \DateTimeImmutable();
        $writer->update($record);
    });
```

The host component delivers the toast on the live channel — no extra code needed.

## Zero consumer JavaScript

The bundle ships one Stimulus controller that drives the notification host. It is
auto-discovered by Symfony StimulusBundle; consumers add a single line to their
`assets/controllers.json`:

```json
{
    "controllers": {
        "@atriumphp/atrium": {
            "notifications": { "enabled": true, "fetch": "eager" }
        }
    }
}
```

A Symfony Flex recipe will automate this in a future release. No custom
JavaScript needs to be written.

---

## API reference

### `Notification`

The main toast builder. Construct with `make()` and chain setters before passing
it to a channel.

| Method | Description |
| --- | --- |
| `make(?string $id = null): self` | Create a notification. `$id` is auto-generated (random hex) when omitted. |
| `title(string $title): self` | **Required.** The toast headline. |
| `body(?string $body): self` | Optional sub-line beneath the title. |
| `success(): self` | Set status to `Success` (green check icon). |
| `danger(): self` | Set status to `Danger` (red × icon). |
| `warning(): self` | Set status to `Warning` (amber triangle icon). |
| `info(): self` | Set status to `Info` (blue info icon). |
| `status(NotificationStatus $status): self` | Set any status directly. |
| `icon(?string $icon): self` | Override the status default icon. |
| `color(?string $color): self` | Override the status default colour. |
| `duration(int $milliseconds): self` | Auto-dismiss delay. Default: `5000`. |
| `persistent(bool $persistent = true): self` | Disable auto-dismiss when `true`; restore default timeout when `false`. |
| `actions(list<NotificationAction> $actions): self` | In-toast action buttons. |
| `toArray(): array` | Serialise to the wire format. Throws `\LogicException` if no `title()` was set. |
| `fromArray(array $data): self` | Reconstruct from a serialised payload. |

**Constant:** `Notification::DURATION_DEFAULT = 5000` — default auto-dismiss
delay in milliseconds.

---

### `NotificationAction`

An in-toast action button. Must declare either `url()` or `emit()` — not both.

| Method | Description |
| --- | --- |
| `make(string $label): self` | Create the action with the button label. |
| `icon(?string $icon): self` | Optional icon on the button. |
| `color(?string $color): self` | Optional button colour. |
| `url(string $url): self` | Make it a **link** action (`<a href>`). Scheme-guarded: `http(s)`, `mailto:`, `tel:`, relative, and root-relative paths are accepted; other explicit schemes are dropped. Works on both channels. |
| `emit(string $event, array $payload = []): self` | Make it an **emit** action. Clicking emits `$event` with `$payload` to Live Component listeners. **Live channel only** — stripped from flash payloads. |
| `closeOnClick(bool $close = true): self` | Whether clicking the button closes the toast (default `true`). |
| `isLink(): bool` | Returns `true` when the action was built with `url()`. |

Throws `\LogicException` if both `url()` and `emit()` are set, or if `toArray()`
is called without either.

---

### `NotificationStatus`

A backed enum that selects the default icon and colour.

| Case | Value | Default icon | Default colour |
| --- | --- | --- | --- |
| `Success` | `'success'` | `circle-check` | `success` |
| `Danger` | `'danger'` | `circle-x` | `danger` |
| `Warning` | `'warning'` | `triangle-alert` | `warning` |
| `Info` | `'info'` | `info` | `info` |
| `Neutral` | `'neutral'` | `bell` | `gray` |

| Method | Description |
| --- | --- |
| `defaultIcon(): string` | The Lucide glyph name for this status. |
| `defaultColor(): string` | The semantic colour name for this status. |

---

### `InteractsWithNotifications` (trait)

Adds three protected helpers to any Live Component. The component must also
`use ComponentToolsTrait` (this trait calls its `emit()`).

| Method | Description |
| --- | --- |
| `notify(Notification $notification): void` | Emit the notification on the live channel immediately. |
| `notifySuccess(string $title, ?string $body = null): void` | Shortcut: build and emit a success toast. |
| `notifyDanger(string $title, ?string $body = null): void` | Shortcut: build and emit a danger toast. |

---

### `Notifier` (service)

The flash-channel bridge. Inject it into controllers and services.

| Method | Description |
| --- | --- |
| `send(Notification $notification): void` | Queue the notification in the Symfony flash bag under `Notifier::FLASH_KEY`. Emit actions are stripped (they cannot survive a redirect). No-op when no session is available (CLI, sub-request). |

**Constant:** `Notifier::FLASH_KEY = 'atrium.notifications'` — the flash-bag key
the `Atrium:Notifications` host component drains on mount.

---

## See also

- [Actions](../actions/overview.md) — `Action::successNotification()` wires a
  toast onto any server action.
- [Forms](../forms/overview.md) — form save raises a success toast by default.
- [Panel customisation](../panel/customization.md) — icons used in toasts follow
  the panel icon vocabulary.
