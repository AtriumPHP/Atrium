# Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a server-driven toast-notification subsystem to Atrium — a fluent `Notification` builder any action/component/controller can raise, delivered via a live channel (no reload) and a Symfony flash bridge (survives a redirect), rendered as stacking auto-dismissing toasts.

**Architecture:** A dependency-free `Atrium\Notification` value layer (`Notification`, `NotificationStatus`, `NotificationAction`) plus two delivery seams — a Live Component trait (`InteractsWithNotifications`, emits a live event) and an injectable `Notifier` service (writes the Symfony flash bag). A single `Atrium:Notifications` Live Component mounted in the layout renders the stack; the bundle ships its first Stimulus controller (timer/animation only), discovered through the standard Symfony UX `assets/package.json` mechanism so consumers write zero JS.

**Tech Stack:** PHP 8.4, Symfony UX Live Components, Symfony UX Icons, StimulusBundle/AssetMapper, Tailwind (precompiled), PHPUnit, PHPStan (max), PHP-CS-Fixer (Symfony ruleset).

**Spec:** `docs/PRDs/PRD-notifications.md`. **Gates (every milestone):** `composer test && composer phpstan && composer cs`.

> **Plan-review corrections folded in (2026-06-07).** A codebase-verified review caught and this plan now reflects: (1) the shipped controller dir must be exposed to AssetMapper in `AtriumBundle::prependExtension()` or discovery *throws* — see Task 4; (2) correct StimulusBundle service id `stimulus.asset_mapper.controllers_map_generator` + `assertArrayHasKey`; (3) `createLiveComponent()` takes the live-component **name** string, not a class; (4) `mount()` must guard `getSession()`; (5) `Form` exposes `getResourceLabel()`, not `resourceLabel()`; (6) **layering:** `FLASH_KEY` lives in the `Atrium\Notification` layer (on `Notifier`), never on the Twig component; (7) emit-actions are dropped when flashing; (9) link-action URLs are scheme-guarded **in PHP** (`NotificationAction::toArray()`), no Twig filter; (10) assert emitted events with `assertComponentEmitEvent(...)`. Verified-good (no change needed): cross-component `emit→#[LiveListener]` routing reaches the layout-mounted host; the `data-action="live#action"` param attributes; `runAction`/`dismiss` forge-resistance; mounting the host once in the layout.

---

## Conventions to mirror (read these first)

- **Builder style:** `src/Action/Action.php` and `src/Table/Column.php` — fluent, `protected` props, `@phpstan-consistent-constructor`, static `make()`. New built-in value objects are `final` unless meant to be extended.
- **Live Component:** `src/Twig/Components/Widget.php` — `#[AsLiveComponent(name: 'Atrium:…', template: '@Atrium/components/…')]`, `DefaultActionTrait`, non-writable `#[LiveProp]` for unforgeable state, constructor DI. Components are auto-registered by `config/services.php:61` (`$services->load('Atrium\\Twig\\Components\\', …)`).
- **URL guard:** `src/Action/Action.php::safeUrl()` (private). We add an equivalent guard for notification link actions (the method is private there, so we reuse the same logic locally — see Task 3).
- **Colour vocabulary:** `src/View/Concern/ResolvesColor.php` — semantic names `gray|info|success|warning|danger|primary` → Tailwind scales `gray|sky|green|amber|red|primary`. Notifications use the same semantic names.
- **Icons:** `atrium_icon('…')` / `ux_icon('…')`; bare names resolve to the shipped `atrium:` Lucide set (`assets/icons/*.svg`). Some glyphs we need are not yet shipped — Task 1 adds them.
- **CSS:** Tailwind is precompiled to `assets/dist/atrium.css` via `composer build-css`. Classes composed in PHP/Twig conditionally must be safelisted with `@source inline("…")` in `assets/atrium.css` or they are purged (this bit us before). Rebuilding requires the standalone CLI (see Task 8 for the exact, verified procedure).
- **Tests:** unit tests under `tests/<Area>/…Test.php` extending `PHPUnit\Framework\TestCase`; functional/live tests under `tests/Functional/…` extending `KernelTestCase` with `InteractsWithLiveComponents`, and a `tearDown` calling `restore_exception_handler()` (kernel boots leak handlers — see `tests/Functional/NoDataProviderTest.php:26`).

---

## File Structure

**New — core value layer (NTF-M1):**
- `src/Notification/Notification.php` — the fluent builder + `toArray`/`fromArray`.
- `src/Notification/NotificationStatus.php` — enum + default icon/colour.
- `src/Notification/NotificationAction.php` — serializable link-or-emit action.

**New — delivery + render (NTF-M2/M3):**
- `src/Notification/Notifier.php` — flash-bag service.
- `src/Twig/Components/Concern/InteractsWithNotifications.php` — live-channel trait.
- `src/Twig/Components/Notifications.php` — the `Atrium:Notifications` render host.
- `templates/components/notifications.html.twig` — the toast stack template.
- `assets/controllers/notifications_controller.js` — the shipped Stimulus controller.
- `assets/package.json` — declares the controller for StimulusBundle discovery.

**Modified:**
- `assets/icons/{circle-check,circle-x,triangle-alert,info,bell}.svg` — glyphs (Task 1).
- `assets/atrium.css` (+ rebuilt `assets/dist/atrium.css`) — colour-class safelist (Task 8).
- `src/AtriumBundle.php` — expose `assets/controllers` to AssetMapper so the shipped controller resolves (Task 4).
- `templates/admin/layout.html.twig` — mount `<twig:Atrium:Notifications/>` (Task 5).
- `src/Twig/Components/Form.php` + `templates/components/form.html.twig` — replace inline success notice with a notification (Task 7).
- `src/Action/Action.php` — optional `successNotification()` (Task 7).
- `config/services.php` — register `Notifier` (Task 6).
- `tests/Functional/app/assets/controllers.json` (new) + `tests/Functional/app/importmap.php` — wire the controller for the asset-exposure test (Task 4).
- `CHANGELOG.md`, `docs/integration-guide/notifications/overview.md`, `docs/PRDs/PRD.md` (Task 9–10).

**Playground (NTF-M4):** a custom action raising a toast + an `Undo` emit listener.

---

# NTF-M1 — Core value objects

No UI. Pure, dependency-free, fully unit-tested value layer.

### Task 1: `NotificationStatus` enum + glyphs

**Files:**
- Create: `src/Notification/NotificationStatus.php`
- Create: `assets/icons/circle-check.svg`, `circle-x.svg`, `triangle-alert.svg`, `info.svg`, `bell.svg` (only those not already present — check first)
- Test: `tests/Notification/NotificationStatusTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Notification;

use Atrium\Notification\NotificationStatus;
use PHPUnit\Framework\TestCase;

final class NotificationStatusTest extends TestCase
{
    public function testEachStatusHasADefaultIconAndSemanticColor(): void
    {
        self::assertSame('circle-check', NotificationStatus::Success->defaultIcon());
        self::assertSame('success', NotificationStatus::Success->defaultColor());

        self::assertSame('circle-x', NotificationStatus::Danger->defaultIcon());
        self::assertSame('danger', NotificationStatus::Danger->defaultColor());

        self::assertSame('triangle-alert', NotificationStatus::Warning->defaultIcon());
        self::assertSame('warning', NotificationStatus::Warning->defaultColor());

        self::assertSame('info', NotificationStatus::Info->defaultIcon());
        self::assertSame('info', NotificationStatus::Info->defaultColor());

        self::assertSame('bell', NotificationStatus::Neutral->defaultIcon());
        self::assertSame('gray', NotificationStatus::Neutral->defaultColor());
    }
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `vendor/bin/phpunit tests/Notification/NotificationStatusTest.php`
Expected: FAIL — `Class "Atrium\Notification\NotificationStatus" not found`.

- [ ] **Step 3: Implement the enum**

```php
<?php

declare(strict_types=1);

namespace Atrium\Notification;

/**
 * The semantic status of a {@see Notification}: it selects a default icon and a
 * semantic colour (the shared `gray|info|success|warning|danger` vocabulary used
 * across tables, content blocks and view entries). Override either on the
 * notification itself.
 */
enum NotificationStatus: string
{
    case Success = 'success';
    case Danger = 'danger';
    case Warning = 'warning';
    case Info = 'info';
    case Neutral = 'neutral';

    /** Default Lucide glyph (shipped `atrium:` icon set). */
    public function defaultIcon(): string
    {
        return match ($this) {
            self::Success => 'circle-check',
            self::Danger => 'circle-x',
            self::Warning => 'triangle-alert',
            self::Info => 'info',
            self::Neutral => 'bell',
        };
    }

    /** Default semantic colour name (resolved to a Tailwind scale at render time). */
    public function defaultColor(): string
    {
        return match ($this) {
            self::Success => 'success',
            self::Danger => 'danger',
            self::Warning => 'warning',
            self::Info => 'info',
            self::Neutral => 'gray',
        };
    }
}
```

- [ ] **Step 4: Add any missing glyphs**

Check which exist: `ls assets/icons`. For each of `circle-check, circle-x, triangle-alert, info, bell` that is **absent**, add the Lucide SVG. Lucide markup (stroke-based, `fill="none"` is forced by the bundle's icon-set config). Example `assets/icons/bell.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8A6 6 0 0 0 6 8c0 4.499-1.411 5.956-2.738 7.326"/></svg>
```

Use the canonical Lucide path data for each (`circle-check`, `circle-x`, `triangle-alert`, `info`, `bell`) from https://lucide.dev. Keep the same `<svg>` attribute header as the existing files in `assets/icons/` (match `assets/icons/check.svg` for attribute order).

- [ ] **Step 5: Run the test — expect pass**

Run: `vendor/bin/phpunit tests/Notification/NotificationStatusTest.php`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add src/Notification/NotificationStatus.php tests/Notification/NotificationStatusTest.php assets/icons/
git commit -m "NTF-M1: NotificationStatus enum + toast glyphs"
```

---

### Task 2: `NotificationAction` (serializable link-or-emit)

**Files:**
- Create: `src/Notification/NotificationAction.php`
- Test: `tests/Notification/NotificationActionTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Notification;

use Atrium\Notification\NotificationAction;
use PHPUnit\Framework\TestCase;

final class NotificationActionTest extends TestCase
{
    public function testLinkActionRoundTrips(): void
    {
        $action = NotificationAction::make('View')
            ->icon('eye')
            ->color('info')
            ->url('https://example.test/articles/1');

        $array = $action->toArray();
        self::assertSame('link', $array['kind']);
        self::assertSame('View', $array['label']);
        self::assertSame('https://example.test/articles/1', $array['url']);
        self::assertTrue($array['closeOnClick']);

        $restored = NotificationAction::fromArray($array);
        self::assertEquals($array, $restored->toArray());
    }

    public function testEmitActionCarriesEventAndPayload(): void
    {
        $action = NotificationAction::make('Undo')->emit('article:undo', ['id' => 42])->closeOnClick(false);

        $array = $action->toArray();
        self::assertSame('emit', $array['kind']);
        self::assertSame('article:undo', $array['event']);
        self::assertSame(['id' => 42], $array['payload']);
        self::assertFalse($array['closeOnClick']);
    }

    public function testAnActionMustBeEitherLinkOrEmit(): void
    {
        $this->expectException(\LogicException::class);
        NotificationAction::make('Broken')->toArray(); // neither url() nor emit()
    }

    public function testLinkAndEmitAreMutuallyExclusive(): void
    {
        $this->expectException(\LogicException::class);
        NotificationAction::make('Broken')->url('https://x.test')->emit('e');
    }

    public function testDangerousLinkSchemesAreNeutralisedInToArray(): void
    {
        // A link URL is scheme-guarded in PHP (no Twig filter needed downstream).
        self::assertNull(NotificationAction::make('x')->url('javascript:alert(1)')->toArray()['url']);
        self::assertNull(NotificationAction::make('x')->url('data:text/html,evil')->toArray()['url']);
        self::assertSame('/admin/articles/1', NotificationAction::make('x')->url('/admin/articles/1')->toArray()['url']);
        self::assertSame('mailto:a@b.test', NotificationAction::make('x')->url('mailto:a@b.test')->toArray()['url']);
    }
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `vendor/bin/phpunit tests/Notification/NotificationActionTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Atrium\Notification;

/**
 * A button inside a {@see Notification} toast. Deliberately lightweight and fully
 * serializable — it is *not* the closure-based {@see \Atrium\Action\Action},
 * because a captured closure survives neither the flash bag nor a signed
 * live-event payload.
 *
 * An action is exactly one of:
 *  - a **link** ({@see url()}) — an `<a href>`, scheme-guarded at render; works in
 *    both the live and flash channels;
 *  - an **emit** ({@see emit()}) — clicking emits a named Live Component event the
 *    app can listen for; live-channel only.
 *
 * Mirror the {@see \Atrium\Action\Action} fluent style.
 *
 * @phpstan-consistent-constructor
 *
 * @phpstan-type NotificationActionArray array{
 *     kind: 'link'|'emit', label: string, icon: ?string, color: ?string,
 *     closeOnClick: bool, url?: ?string, event?: string, payload?: array<string, scalar|null>
 * }
 */
final class NotificationAction
{
    private ?string $icon = null;
    private ?string $color = null;
    private bool $closeOnClick = true;

    private ?string $url = null;
    private ?string $event = null;
    /** @var array<string, scalar|null> */
    private array $payload = [];

    private function __construct(private readonly string $label)
    {
    }

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function url(string $url): self
    {
        if (null !== $this->event) {
            throw new \LogicException('A notification action is either a link or an emit, not both.');
        }
        $this->url = $url;

        return $this;
    }

    /**
     * @param array<string, scalar|null> $payload
     */
    public function emit(string $event, array $payload = []): self
    {
        if (null !== $this->url) {
            throw new \LogicException('A notification action is either a link or an emit, not both.');
        }
        $this->event = $event;
        $this->payload = $payload;

        return $this;
    }

    public function closeOnClick(bool $close = true): self
    {
        $this->closeOnClick = $close;

        return $this;
    }

    public function isLink(): bool
    {
        return null !== $this->url;
    }

    /**
     * @return NotificationActionArray
     */
    public function toArray(): array
    {
        if (null !== $this->url) {
            return [
                'kind' => 'link',
                'label' => $this->label,
                'icon' => $this->icon,
                'color' => $this->color,
                'closeOnClick' => $this->closeOnClick,
                // Scheme-guarded in PHP so the template prints `action.url` directly
                // (no Twig filter). Mirrors Action::safeUrl()/Entry::safeUrl().
                'url' => self::safeUrl($this->url),
            ];
        }

        if (null !== $this->event) {
            return [
                'kind' => 'emit',
                'label' => $this->label,
                'icon' => $this->icon,
                'color' => $this->color,
                'closeOnClick' => $this->closeOnClick,
                'event' => $this->event,
                'payload' => $this->payload,
            ];
        }

        throw new \LogicException('A notification action must declare either url() or emit().');
    }

    /**
     * @param NotificationActionArray $data
     */
    public static function fromArray(array $data): self
    {
        $action = self::make($data['label'])
            ->icon($data['icon'] ?? null)
            ->color($data['color'] ?? null)
            ->closeOnClick($data['closeOnClick'] ?? true);

        if ('link' === $data['kind']) {
            // A guarded-away url may serialize to null; reconstruct as a dead anchor.
            return $action->url(\is_string($data['url'] ?? null) ? $data['url'] : '#');
        }

        return $action->emit($data['event'], $data['payload'] ?? []);
    }

    /**
     * Reject non-navigational schemes (`javascript:`, `data:`, …); allow
     * http(s)/mailto/tel and relative/anchor/query URLs. Same policy as
     * {@see \Atrium\Action\Action::safeUrl()} (kept local to avoid a sideways dep;
     * a future refactor may extract the three copies to one shared guard).
     */
    private static function safeUrl(?string $url): ?string
    {
        if (null === $url || '' === $url) {
            return null;
        }

        $scheme = parse_url($url, \PHP_URL_SCHEME);
        if (null === $scheme || false === $scheme) {
            return $url; // relative / anchor / query — safe
        }

        return \in_array(strtolower((string) $scheme), ['http', 'https', 'mailto', 'tel'], true) ? $url : null;
    }
}
```

> Copy the exact `safeUrl()` body from `src/Action/Action.php:372` so the policy is identical (it already handles the edge cases this version sketches). The plan duplicates rather than shares it because `Action::safeUrl()` is `private`; Should-fix item in the review notes a later refactor to one shared guard.

- [ ] **Step 4: Run — expect pass**

Run: `vendor/bin/phpunit tests/Notification/NotificationActionTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Notification/NotificationAction.php tests/Notification/NotificationActionTest.php
git commit -m "NTF-M1: NotificationAction (serializable link/emit)"
```

---

### Task 3: `Notification` builder + serialization

**Files:**
- Create: `src/Notification/Notification.php`
- Test: `tests/Notification/NotificationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Notification;

use Atrium\Notification\Notification;
use Atrium\Notification\NotificationAction;
use Atrium\Notification\NotificationStatus;
use PHPUnit\Framework\TestCase;

final class NotificationTest extends TestCase
{
    public function testStatusShortcutSetsDefaultIconAndColor(): void
    {
        $array = Notification::make('id-1')->title('Saved')->success()->toArray();

        self::assertSame('id-1', $array['id']);
        self::assertSame('Saved', $array['title']);
        self::assertSame('success', $array['status']);
        self::assertSame('circle-check', $array['icon']);
        self::assertSame('success', $array['color']);
        self::assertNull($array['body']);
        self::assertSame(Notification::DURATION_DEFAULT, $array['duration']);
        self::assertSame([], $array['actions']);
    }

    public function testIconAndColorOverridesWin(): void
    {
        $array = Notification::make('id-2')->title('Hi')->success()->icon('rocket')->color('primary')->toArray();

        self::assertSame('rocket', $array['icon']);
        self::assertSame('primary', $array['color']);
    }

    public function testPersistentClearsDurationAndDurationClearsPersistent(): void
    {
        self::assertNull(Notification::make('a')->title('x')->persistent()->toArray()['duration']);
        // duration() after persistent() re-enables auto-dismiss
        self::assertSame(3000, Notification::make('b')->title('x')->persistent()->duration(3000)->toArray()['duration']);
    }

    public function testAutoGeneratesAnIdWhenNoneGiven(): void
    {
        $id = Notification::make()->title('x')->toArray()['id'];
        self::assertNotSame('', $id);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $id);
    }

    public function testActionsAreSerialized(): void
    {
        $array = Notification::make('c')->title('x')->actions([
            NotificationAction::make('View')->url('https://x.test'),
            NotificationAction::make('Undo')->emit('undo'),
        ])->toArray();

        self::assertCount(2, $array['actions']);
        self::assertSame('link', $array['actions'][0]['kind']);
        self::assertSame('emit', $array['actions'][1]['kind']);
    }

    public function testRoundTripsThroughFromArray(): void
    {
        $original = Notification::make('d')->title('Saved')->body('All good')->warning()
            ->duration(8000)
            ->actions([NotificationAction::make('View')->url('https://x.test')])
            ->toArray();

        self::assertEquals($original, Notification::fromArray($original)->toArray());
    }

    public function testFromArrayRejectsMalformedInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Notification::fromArray(['id' => 'x']); // missing title/status
    }

    public function testStatusDefaultsToNeutral(): void
    {
        $array = Notification::make('e')->title('x')->toArray();
        self::assertSame(NotificationStatus::Neutral->value, $array['status']);
        self::assertSame('bell', $array['icon']);
    }
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `vendor/bin/phpunit tests/Notification/NotificationTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Atrium\Notification;

/**
 * A toast notification — title, optional body, a semantic {@see NotificationStatus}
 * (driving a default icon + colour), an auto-dismiss duration (or persistent), and
 * optional {@see NotificationAction} buttons.
 *
 * Raise one from a Live Component with the {@see \Atrium\Twig\Components\Concern\InteractsWithNotifications}
 * trait (live channel, no reload) or from anywhere via the {@see Notifier} service
 * (flash bridge, survives a redirect). Mirror the {@see \Atrium\Table\Column} /
 * {@see \Atrium\Action\Action} fluent builder style.
 *
 * @phpstan-consistent-constructor
 *
 * @phpstan-import-type NotificationActionArray from NotificationAction
 *
 * @phpstan-type NotificationArray array{
 *     id: string, title: string, body: ?string, status: string, icon: string,
 *     color: string, duration: ?int, actions: list<NotificationActionArray>
 * }
 */
final class Notification
{
    /** Default auto-dismiss delay, in milliseconds. */
    public const DURATION_DEFAULT = 5000;

    private string $title = '';
    private ?string $body = null;
    private NotificationStatus $status = NotificationStatus::Neutral;
    private ?string $icon = null;
    private ?string $color = null;
    private ?int $duration = self::DURATION_DEFAULT;
    /** @var list<NotificationAction> */
    private array $actions = [];

    private function __construct(private readonly string $id)
    {
    }

    public static function make(?string $id = null): self
    {
        return new self($id ?? bin2hex(random_bytes(8)));
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function body(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    public function status(NotificationStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function success(): self
    {
        return $this->status(NotificationStatus::Success);
    }

    public function danger(): self
    {
        return $this->status(NotificationStatus::Danger);
    }

    public function warning(): self
    {
        return $this->status(NotificationStatus::Warning);
    }

    public function info(): self
    {
        return $this->status(NotificationStatus::Info);
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function color(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function duration(int $milliseconds): self
    {
        $this->duration = $milliseconds;

        return $this;
    }

    public function persistent(bool $persistent = true): self
    {
        $this->duration = $persistent ? null : self::DURATION_DEFAULT;

        return $this;
    }

    /**
     * @param list<NotificationAction> $actions
     */
    public function actions(array $actions): self
    {
        $this->actions = array_values($actions);

        return $this;
    }

    /**
     * @return NotificationArray
     */
    public function toArray(): array
    {
        if ('' === $this->title) {
            throw new \LogicException('A notification requires a title().');
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status->value,
            'icon' => $this->icon ?? $this->status->defaultIcon(),
            'color' => $this->color ?? $this->status->defaultColor(),
            'duration' => $this->duration,
            'actions' => array_map(static fn (NotificationAction $a): array => $a->toArray(), $this->actions),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['id'], $data['title'], $data['status']) || !\is_string($data['id']) || !\is_string($data['title'])) {
            throw new \InvalidArgumentException('Malformed notification payload.');
        }

        $status = NotificationStatus::tryFrom((string) $data['status']) ?? NotificationStatus::Neutral;

        $notification = self::make($data['id'])->title($data['title'])->status($status);

        if (isset($data['body']) && \is_string($data['body'])) {
            $notification->body($data['body']);
        }
        if (isset($data['icon']) && \is_string($data['icon'])) {
            $notification->icon($data['icon']);
        }
        if (isset($data['color']) && \is_string($data['color'])) {
            $notification->color($data['color']);
        }
        if (\array_key_exists('duration', $data)) {
            $notification->duration = null === $data['duration'] ? null : (int) $data['duration'];
        }
        if (isset($data['actions']) && \is_array($data['actions'])) {
            $notification->actions(array_map(
                /** @param NotificationActionArray $a */
                static fn (array $a): NotificationAction => NotificationAction::fromArray($a),
                array_values($data['actions']),
            ));
        }

        return $notification;
    }
}
```

> **Note on `id`:** `bin2hex(random_bytes(8))` is fine in PHP (the `Math.random/Date` ban in the harness applies only to workflow scripts, not to production PHP). Tests pass an explicit id for determinism.

- [ ] **Step 4: Run — expect pass**

Run: `vendor/bin/phpunit tests/Notification/NotificationTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Full gates + commit**

```bash
composer test && composer phpstan && composer cs
git add src/Notification/Notification.php tests/Notification/NotificationTest.php
git commit -m "NTF-M1: Notification builder + serialization"
```

Expected: all green; PHPStan max clean; CS clean.

---

# NTF-M2 — Render host + Stimulus controller + live channel

The toast UI: the `Atrium:Notifications` Live Component, the shipped Stimulus controller (+ its discovery wiring), the layout mount, the live-channel trait, and dismiss/run-action.

### Task 4: Ship the Stimulus controller + StimulusBundle discovery

This is the flagged integration risk — pinned down here. The mechanism (verified against `vendor/symfony/stimulus-bundle/src/Ux/UxPackageReader.php`): StimulusBundle's `ControllersMapGenerator` reads the **app's** `assets/controllers.json`; each `@<vendor>/<package>` entry is resolved to the installed Composer package path via `Composer\InstalledVersions`, then its `assets/package.json` (or `Resources/assets/package.json`) `symfony.controllers` block is read. So Atrium ships `assets/package.json` + the controller file; the consumer adds **one entry** (config, not JS) to their `controllers.json` (a Flex recipe can automate this in Phase 7).

**Files:**
- Create: `assets/controllers/notifications_controller.js`
- Create: `assets/package.json`
- Modify: `src/AtriumBundle.php` (expose `assets/controllers` to AssetMapper)
- Create: `tests/Functional/app/assets/controllers.json`
- Test: `tests/Functional/NotificationsAssetTest.php`

> **Already in place (verified — do NOT re-add):** `symfony/stimulus-bundle` is installed (transitive via ux-live-component, in `composer.lock`); `Symfony\UX\StimulusBundle\StimulusBundle` is already registered in `tests/Functional/AtriumTestKernel.php`; AssetMapper is configured with `paths: %kernel.project_dir%/assets` and the test app's project dir is `tests/Functional/app`, so StimulusBundle's default `controllers_json` of `%kernel.project_dir%/assets/controllers.json` is exactly where Step 5 creates it. No `composer require`, no kernel/bundle edits, no `importmap.php` change needed.

- [ ] **Step 1: Write the failing test** (proves the controller is discoverable by StimulusBundle in the test app)

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\StimulusBundle\AssetMapper\ControllersMapGenerator;

/**
 * The bundle ships its first Stimulus controller (toast timing/animation). Prove
 * StimulusBundle discovers it for an app that references the package in its
 * controllers.json — so the consumer enables it with one config line and no JS.
 */
final class NotificationsAssetTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testNotificationsControllerIsDiscovered(): void
    {
        self::bootKernel();
        // Service id verified against vendor/symfony/stimulus-bundle/config/services.php:47.
        // It's private, but the test container exposes private services.
        $generator = self::getContainer()->get('stimulus.asset_mapper.controllers_map_generator');
        self::assertInstanceOf(ControllersMapGenerator::class, $generator);

        // getControllersMap() returns array<string name, MappedControllerAsset>
        // (MappedControllerAsset has no ->name property), so assert on the key.
        self::assertArrayHasKey('atrium--notifications', $generator->getControllersMap());
    }
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `vendor/bin/phpunit tests/Functional/NotificationsAssetTest.php`
Expected: FAIL — `atrium--notifications` not in the map (the controller file, `assets/package.json`, the AssetMapper path, and the test app's `controllers.json` don't exist yet). StimulusBundle + AssetMapper are already booted (see the "Already in place" note).

- [ ] **Step 3: Write the controller** (`assets/controllers/notifications_controller.js`)

Plain ES module — no build step; AssetMapper serves it as-is.

```js
import { Controller } from '@hotwired/stimulus';

/*
 * Atrium toast controller — client-local concerns only (timing + animation).
 * All notification content and state is server-authored by the Atrium:Notifications
 * Live Component; this controller never creates notifications.
 *
 * Each toast element carries:
 *   data-atrium--notifications-target="toast"
 *   data-notification-id="<id>"
 *   data-duration="<ms|''>"   (empty = persistent)
 * The dismiss action is the Live Component's `dismiss(id)`, triggered by the timer
 * or the close button (which calls dismiss via the Live action attribute already
 * in the template). This controller only animates and arms/pauses the timer.
 */
export default class extends Controller {
    static targets = ['toast'];

    toastTargetConnected(el) {
        // Animate in on next frame.
        requestAnimationFrame(() => el.classList.remove('opacity-0', 'translate-y-2'));

        const duration = parseInt(el.dataset.duration || '', 10);
        if (!Number.isNaN(duration) && duration > 0) {
            this._arm(el, duration);
            el.addEventListener('mouseenter', () => this._pause(el));
            el.addEventListener('mouseleave', () => this._arm(el, duration));
        }
    }

    _arm(el, duration) {
        this._pause(el);
        el._atriumTimer = window.setTimeout(() => this._dismiss(el), duration);
    }

    _pause(el) {
        if (el._atriumTimer) {
            window.clearTimeout(el._atriumTimer);
            el._atriumTimer = null;
        }
    }

    _dismiss(el) {
        // Fade out, then click the server-side dismiss trigger embedded in the toast.
        el.classList.add('opacity-0', 'translate-y-2');
        const trigger = el.querySelector('[data-atrium-dismiss]');
        window.setTimeout(() => trigger && trigger.click(), 150);
    }
}
```

- [ ] **Step 4: Declare it for discovery** (`assets/package.json`)

Mirror `vendor/symfony/ux-live-component/assets/package.json`'s `symfony.controllers` shape.

```json
{
    "name": "@atriumphp/atrium",
    "description": "Atrium admin panel — shipped Stimulus controllers.",
    "license": "MIT",
    "type": "module",
    "symfony": {
        "controllers": {
            "notifications": {
                "main": "controllers/notifications_controller.js",
                "name": "atrium--notifications",
                "fetch": "eager",
                "enabled": true
            }
        },
        "importmap": {
            "@hotwired/stimulus": "^3.0.0"
        }
    }
}
```

- [ ] **Step 4b: Expose `assets/controllers` to AssetMapper** (`src/AtriumBundle.php`)

**Critical (Blocker 1):** `ControllersMapGenerator` resolves the controller's `main` via `AssetMapper::getAssetFromSourcePath()`; if the file isn't under a mapped path it **throws** `Could not find an asset mapper path that points to the "notifications" controller`. Today the bundle maps only `assets/dist` (`prependExtension()`, around line 82–86). Add the controllers dir:

```php
        $container->extension('framework', [
            'asset_mapper' => [
                'paths' => [
                    \dirname(__DIR__).'/assets/dist' => 'atrium',
                    \dirname(__DIR__).'/assets/controllers' => 'atrium_controllers',
                ],
            ],
        ]);
```

(The namespace label is irrelevant to controller resolution — the generator scans all mapped paths; it just must cover the file. Keep `assets/dist → atrium` unchanged so the stylesheet URL is stable.)

- [ ] **Step 5: Reference the package from the test app** (`tests/Functional/app/assets/controllers.json`, new)

```json
{
    "controllers": {
        "@atriumphp/atrium": {
            "notifications": {
                "enabled": true,
                "fetch": "eager"
            }
        }
    },
    "entrypoints": []
}
```

No kernel/bundle/composer changes (see "Already in place" note above). The test app's project dir is `tests/Functional/app`, so this is the default `controllers_json` location.

- [ ] **Step 6: Run the test — expect pass**

Run: `vendor/bin/phpunit tests/Functional/NotificationsAssetTest.php`
Expected: PASS — `atrium--notifications` is a key in the controllers map.

- [ ] **Step 7: Commit**

```bash
git add assets/controllers/ assets/package.json src/AtriumBundle.php tests/Functional/app/assets/controllers.json tests/Functional/NotificationsAssetTest.php
git commit -m "NTF-M2: ship notifications Stimulus controller + AssetMapper/discovery wiring"
```

---

### Task 5: `Atrium:Notifications` host component + template + live channel

**Files:**
- Create: `src/Twig/Components/Notifications.php`
- Create: `src/Twig/Components/Concern/InteractsWithNotifications.php`
- Create: `templates/components/notifications.html.twig`
- Modify: `templates/admin/layout.html.twig`
- Test: `tests/Functional/NotificationsComponentTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\Notifications;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class NotificationsComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testAppendsALiveNotificationAndDismissesIt(): void
    {
        // createLiveComponent() takes the registered component NAME, not a class
        // (see vendor InteractsWithLiveComponents + existing FormComponentTest).
        $component = $this->createLiveComponent('Atrium:Notifications');

        // Simulate the live event a trait-using component emits.
        $component->call('receive', [
            'notification' => [
                'id' => 'abc', 'title' => 'Saved', 'body' => null,
                'status' => 'success', 'icon' => 'circle-check', 'color' => 'success',
                'duration' => 5000, 'actions' => [],
            ],
        ]);
        self::assertStringContainsString('Saved', $component->render()->toString());

        $component->call('dismiss', ['id' => 'abc']);
        self::assertStringNotContainsString('Saved', $component->render()->toString());
    }
}
```

> Confirm the exact `InteractsWithLiveComponents` API shape against an existing live test in `tests/Functional/` (e.g. the DataTable/Form live tests) — `createLiveComponent`/`call`/`render` names and the argument-passing convention. Match that test's idioms exactly.

- [ ] **Step 2: Run it — expect failure**

Run: `vendor/bin/phpunit tests/Functional/NotificationsComponentTest.php`
Expected: FAIL — `Atrium\Twig\Components\Notifications` not found.

- [ ] **Step 3: Implement the host component**

```php
<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Notification\Notification;
use Atrium\Notification\Notifier;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The single toast host, mounted once in the panel layout. It holds the active
 * notification stack as server state and renders it; the shipped Stimulus
 * controller handles only client-side timing/animation.
 *
 * Two intake paths:
 *  - live: another component emits `atrium:notification` → {@see receive()};
 *  - flash: {@see mount()} drains the Symfony flash bag (filled by {@see \Atrium\Notification\Notifier}).
 *
 * The stack is a non-writable LiveProp (HMAC-signed), so a client can never forge
 * or mutate notifications — only the server-side listener / flash drain append.
 */
#[AsLiveComponent(name: 'Atrium:Notifications', template: '@Atrium/components/notifications.html.twig')]
final class Notifications
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    // The flash-bag key lives in the Atrium\Notification layer (Notifier::FLASH_KEY)
    // so the value/service layer never depends upward on this Twig component.

    /**
     * The active stack. Non-writable → checksummed; mutated only server-side.
     *
     * @var list<array<string, mixed>>
     */
    #[LiveProp]
    public array $notifications = [];

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function mount(): void
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException) {
            return; // no session (CLI / sub-request / test without one) — nothing to drain
        }

        if (!$session->isStarted() && !$session->getFlashBag()->has(Notifier::FLASH_KEY)) {
            return;
        }

        /** @var list<array<string, mixed>> $flashed */
        $flashed = $session->getFlashBag()->get(Notifier::FLASH_KEY);
        foreach ($flashed as $payload) {
            $this->append($payload);
        }
    }

    /**
     * Live intake: append the payload carried by the `atrium:notification` event.
     *
     * @param array<string, mixed> $notification
     */
    #[LiveListener('atrium:notification')]
    public function receive(#[LiveArg] array $notification): void
    {
        $this->append($notification);
    }

    #[LiveAction]
    public function dismiss(#[LiveArg] string $id): void
    {
        $this->notifications = array_values(array_filter(
            $this->notifications,
            static fn (array $n): bool => ($n['id'] ?? null) !== $id,
        ));
    }

    /**
     * Run a held emit-action: re-emit its named event (safe — only actions the
     * server actually rendered can fire).
     */
    #[LiveAction]
    public function runAction(#[LiveArg] string $id, #[LiveArg] int $index): void
    {
        foreach ($this->notifications as $n) {
            if (($n['id'] ?? null) !== $id) {
                continue;
            }
            $action = $n['actions'][$index] ?? null;
            if (\is_array($action) && 'emit' === ($action['kind'] ?? null) && \is_string($action['event'] ?? null)) {
                $payload = \is_array($action['payload'] ?? null) ? $action['payload'] : [];
                $this->emit($action['event'], $payload);
                if ($action['closeOnClick'] ?? true) {
                    $this->dismiss($id);
                }
            }

            return;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function append(array $payload): void
    {
        // Normalise through the value object so a malformed payload can't render.
        try {
            $this->notifications[] = Notification::fromArray($payload)->toArray();
        } catch (\InvalidArgumentException) {
            // drop silently — server-authored payloads should never be malformed
        }
    }
}
```

> Symbols confirmed by the review against the installed `vendor/symfony/ux-live-component`: `#[LiveListener]` (extends `LiveAction`, so `call('receive', …)` works in tests), `#[LiveArg]`, and `ComponentToolsTrait::emit()`/`emitUp()`/`emitSelf()` all exist; a plain `emit('atrium:notification')` reaches **all** registered components with a matching `#[LiveListener]` (live_controller.js `findComponents(this, false, null)`), so it reaches the layout-mounted host even though it's a different component.

- [ ] **Step 4: Implement the live-channel trait** (`src/Twig/Components/Concern/InteractsWithNotifications.php`)

```php
<?php

declare(strict_types=1);

namespace Atrium\Twig\Components\Concern;

use Atrium\Notification\Notification;

/**
 * Gives a Live Component a one-liner to raise a toast on the live channel (no
 * reload): emits the `atrium:notification` event the {@see \Atrium\Twig\Components\Notifications}
 * host listens for. Requires the host component's {@see \Symfony\UX\LiveComponent\ComponentToolsTrait}
 * (the using component must also `use ComponentToolsTrait;`).
 */
trait InteractsWithNotifications
{
    protected function notify(Notification $notification): void
    {
        $this->emit('atrium:notification', ['notification' => $notification->toArray()]);
    }

    protected function notifySuccess(string $title, ?string $body = null): void
    {
        $this->notify(Notification::make()->title($title)->body($body)->success());
    }

    protected function notifyDanger(string $title, ?string $body = null): void
    {
        $this->notify(Notification::make()->title($title)->body($body)->danger());
    }
}
```

> `emit()` comes from `ComponentToolsTrait`. Document (PHPStan may need a `@method` hint on the trait or an `abstract` declaration) that the consumer component also uses `ComponentToolsTrait`. If PHPStan complains about the undefined `emit()` in the trait context, add `/** @method void emit(string $name, array $data = []) */` to the trait docblock.

- [ ] **Step 5: Implement the template** (`templates/components/notifications.html.twig`)

```twig
{# Toast stack. Fixed top-right; the shipped Stimulus controller times + animates. #}
<div
    {{ attributes }}
    data-controller="atrium--notifications"
    class="pointer-events-none fixed top-4 right-4 z-50 flex w-full max-w-sm flex-col gap-3"
    aria-live="polite"
    aria-atomic="false"
>
    {% for n in this.notifications %}
        {% set scale = {
            'success': 'green', 'danger': 'red', 'warning': 'amber',
            'info': 'sky', 'gray': 'gray', 'primary': 'primary'
        }[n.color] ?? 'gray' %}
        <div
            data-atrium--notifications-target="toast"
            data-notification-id="{{ n.id }}"
            data-duration="{{ n.duration is null ? '' : n.duration }}"
            class="pointer-events-auto translate-y-2 rounded-2xl border border-gray-200 bg-white p-4 opacity-0 shadow-lg transition duration-150 ease-out dark:border-gray-800 dark:bg-gray-900"
            role="status"
        >
            <div class="flex items-start gap-3">
                <span class="text-{{ scale }}-500 dark:text-{{ scale }}-400">{{ atrium_icon(n.icon, {class: 'h-5 w-5'}) }}</span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ n.title }}</p>
                    {% if n.body %}<p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ n.body }}</p>{% endif %}
                    {% if n.actions is not empty %}
                        <div class="mt-2 flex gap-2">
                            {% for action in n.actions %}
                                {% if action.kind == 'link' and action.url %}
                                    {# action.url is already scheme-guarded in NotificationAction::toArray() — no Twig filter. #}
                                    <a href="{{ action.url }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">{{ action.label }}</a>
                                {% elseif action.kind == 'emit' %}
                                    <button type="button" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400"
                                        data-action="live#action" data-live-action-param="runAction" data-live-id-param="{{ n.id }}" data-live-index-param="{{ loop.index0 }}">{{ action.label }}</button>
                                {% endif %}
                            {% endfor %}
                        </div>
                    {% endif %}
                </div>
                <button type="button" aria-label="Dismiss" data-atrium-dismiss
                    class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                    data-action="live#action" data-live-action-param="dismiss" data-live-id-param="{{ n.id }}">
                    {{ atrium_icon('x-mark', {class: 'h-4 w-4'}) }}
                </button>
            </div>
        </div>
    {% endfor %}
</div>
```

> Live-action attributes verified by the review against `templates/components/confirm_modal.html.twig`, `_record_table.html.twig:102-104`, `action.html.twig:58`: `data-action="live#action"` + `data-live-action-param="<method>"` + one `data-live-<arg>-param="<value>"` per `#[LiveArg]` is correct for the installed UX version. No `atrium_safe_url` Twig filter is needed — link URLs are guarded in PHP (`NotificationAction::toArray()`, Task 2), so the template prints `action.url` directly.

- [ ] **Step 6: Mount in the layout** (`templates/admin/layout.html.twig`, just before `</body>` at line ~92)

```twig
        <twig:Atrium:Notifications />
    </body>
```

- [ ] **Step 7: Run the component test — expect pass**

Run: `vendor/bin/phpunit tests/Functional/NotificationsComponentTest.php`
Expected: PASS.

- [ ] **Step 8: Full gates + commit**

```bash
composer test && composer phpstan && composer cs
git add src/Twig/Components/Notifications.php src/Twig/Components/Concern/InteractsWithNotifications.php templates/components/notifications.html.twig templates/admin/layout.html.twig tests/Functional/NotificationsComponentTest.php
git commit -m "NTF-M2: Atrium:Notifications host + live channel + layout mount"
```

> If `composer cs`/`phpstan` flag the `atrium_safe_url` filter or `emit()` typing, fix per Steps 4–5 notes before committing.

---

# NTF-M3 — Flash bridge + flow wiring

### Task 6: `Notifier` flash service

**Files:**
- Create: `src/Notification/Notifier.php`
- Modify: `config/services.php`
- Test: `tests/Functional/NotifierTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Notification\Notification;
use Atrium\Notification\NotificationAction;
use Atrium\Notification\Notifier;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class NotifierTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testSendPushesOntoTheFlashBagUnderTheSharedKey(): void
    {
        self::bootKernel();
        $requestStack = new RequestStack();
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        $notifier = new Notifier($requestStack);
        $notifier->send(Notification::make('x')->title('Saved')->success());

        $flashed = $session->getFlashBag()->peek(Notifier::FLASH_KEY);
        self::assertCount(1, $flashed);
        self::assertSame('Saved', $flashed[0]['title']);
    }

    public function testFlashingDropsEmitActionsButKeepsLinks(): void
    {
        // PRD §8: emit-actions are live-channel-only; the flash bridge keeps link
        // actions only (an emit button can't meaningfully survive a redirect).
        $requestStack = new RequestStack();
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        (new Notifier($requestStack))->send(
            Notification::make('y')->title('Saved')->actions([
                NotificationAction::make('View')->url('/admin/x/1'),
                NotificationAction::make('Undo')->emit('x:undo'),
            ]),
        );

        $flashed = $session->getFlashBag()->peek(Notifier::FLASH_KEY);
        self::assertCount(1, $flashed[0]['actions']);
        self::assertSame('link', $flashed[0]['actions'][0]['kind']);
    }

    public function testSendIsANoOpWithoutASession(): void
    {
        $notifier = new Notifier(new RequestStack());
        $notifier->send(Notification::make('x')->title('Saved'));
        $this->expectNotToPerformAssertions();
    }
}
```

- [ ] **Step 2: Run it — expect failure**

Run: `vendor/bin/phpunit tests/Functional/NotifierTest.php`
Expected: FAIL — `Atrium\Notification\Notifier` not found.

- [ ] **Step 3: Implement**

```php
<?php

declare(strict_types=1);

namespace Atrium\Notification;

use Atrium\Twig\Components\Notifications;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Raises a notification on the **flash channel**: it queues the notification in
 * the Symfony flash bag, so it appears after the next redirect (the
 * {@see Notifications} host drains the bag on mount). Inject it into controllers
 * and services; for an in-place toast from a Live Component, use the
 * {@see \Atrium\Twig\Components\Concern\InteractsWithNotifications} trait instead.
 *
 * No session (CLI, sub-request) → a silent no-op rather than an error.
 */
final class Notifier
{
    /**
     * Flash-bag key the {@see \Atrium\Twig\Components\Notifications} host drains on
     * mount. Defined here in the notification layer so nothing below depends upward
     * on the Twig component (CLAUDE.md rule #2).
     */
    public const FLASH_KEY = 'atrium.notifications';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function send(Notification $notification): void
    {
        try {
            $session = $this->requestStack->getSession();
        } catch (SessionNotFoundException) {
            return;
        }

        // PRD §8: the flash channel keeps link actions only — an emit-action can't
        // survive a redirect (its event reaches no live listener on the new page).
        $payload = $notification->toArray();
        $payload['actions'] = array_values(array_filter(
            $payload['actions'],
            static fn (array $action): bool => 'link' === $action['kind'],
        ));

        $session->getFlashBag()->add(self::FLASH_KEY, $payload);
    }
}
```

- [ ] **Step 4: Register the service** (`config/services.php`)

Add near the other `$services->set(...)` definitions:

```php
    $services->set(\Atrium\Notification\Notifier::class)
        ->args([service('request_stack')])
        ->public();
```

> Match the file's existing style (it uses explicit `->args([...])`, not autowiring — confirm against the `PanelAssetsExtension` registration block). Make it `public()` only if the codebase exposes services that way; otherwise leave private and rely on autowiring into consumers.

- [ ] **Step 5: Run — expect pass**

Run: `vendor/bin/phpunit tests/Functional/NotifierTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Notification/Notifier.php config/services.php tests/Functional/NotifierTest.php
git commit -m "NTF-M3: Notifier flash-bridge service"
```

---

### Task 7: Wire Form save + delete + `Action::successNotification()`

**Files:**
- Modify: `src/Twig/Components/Form.php`
- Modify: `templates/components/form.html.twig`
- Modify: `src/Action/Action.php`
- Test: `tests/Functional/FormNotificationTest.php` (new) + update any existing Form test asserting the old inline notice.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

// Mirror the existing Form live test setup (see tests/Functional for the Form
// component test already present). Arrange a saveable resource, submit valid data,
// and assert the success notification is emitted on the live channel.

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class FormNotificationTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testSuccessfulSaveEmitsASuccessNotification(): void
    {
        // Arrange: copy the exact mount/arrange block from tests/Functional/FormComponentTest.php
        // (createLiveComponent('Atrium:Form', [...]) with a saveable test resource),
        // set valid formData, then:
        $component = $this->createLiveComponent('Atrium:Form', [/* resource + entityId per FormComponentTest */]);
        // ... set valid formData via ->set(...) as FormComponentTest does ...
        $component->call('save');

        // assertComponentEmitEvent() exists on InteractsWithLiveComponents
        // (vendor .../Test/InteractsWithLiveComponents.php:50; TestLiveComponent::getEmittedEvents()).
        self::assertComponentEmitEvent($component, 'atrium:notification');
    }
}
```

> **Before implementing:** open `tests/Functional/FormComponentTest.php` and copy its exact `createLiveComponent('Atrium:Form', …)` arrange block + `formData` set calls. The assertion API is confirmed: `assertComponentEmitEvent($component, 'atrium:notification')` (and `getEmittedEvents()` for the payload). No placeholder remains.

- [ ] **Step 2: Run it — expect failure/incomplete**

Run: `vendor/bin/phpunit tests/Functional/FormNotificationTest.php`
Expected: INCOMPLETE, then (after writing the real body) FAIL — no notification emitted yet.

- [ ] **Step 3: Wire `Form::save()`** (`src/Twig/Components/Form.php`)

- Add `use Atrium\Twig\Components\Concern\InteractsWithNotifications;` to the class (it already uses `ComponentToolsTrait`, which the trait needs).
- In the success branch of `save()` (around line 245–260, where it currently sets the inline status / emits `notifyEvent` / redirects), raise a notification:
  - On **redirect** (a page hook returns a URL): use the flash channel so it shows on the destination — inject `Notifier` (constructor) and call `$this->notifier->send(Notification::make()->title(sprintf('%s saved', $this->getResourceLabel()))->success());` **before** issuing the redirect.
  - On **stay** (no redirect): `$this->notify(Notification::make()->title(sprintf('%s saved', $this->getResourceLabel()))->success());`

  > `Form` exposes `getResourceLabel()` (line 548) — **not** `resourceLabel()`. The inline success block to remove is `templates/components/form.html.twig:8-15` (`{{ this.resourceLabel }} saved successfully.`); no test asserts that text (grep confirmed), so removing it breaks nothing. Preserve the embedded-save `emitUp($this->notifyEvent, …)` at `Form.php:254`.
- Remove the code path that set the inline `formStatus`/success flag now superseded by the toast (keep error handling untouched).

Exact edit: read `save()` first; preserve the embedded-save `emitUp($this->notifyEvent, …)` behaviour (relation-manager modals rely on it) and only swap the *success-notice* surface for a notification.

- [ ] **Step 4: Remove the inline success panel** (`templates/components/form.html.twig`, around line 13 `… saved successfully.`)

Delete the inline success block. Leave validation-error rendering intact.

- [ ] **Step 5: Add `Action::successNotification()`** (`src/Action/Action.php`)

```php
    protected ?string $successNotificationTitle = null;
    protected ?string $successNotificationBody = null;

    public function successNotification(string $title, ?string $body = null): static
    {
        $this->successNotificationTitle = $title;
        $this->successNotificationBody = $body;

        return $this;
    }

    public function getSuccessNotificationTitle(): ?string
    {
        return $this->successNotificationTitle;
    }

    public function getSuccessNotificationBody(): ?string
    {
        return $this->successNotificationBody;
    }
```

Then raise the toast at the single point where a server action handler returns successfully. **Verified wiring (review item 8):** `InteractsWithActions` is used by `RecordActions`, `AbstractRecordTable` (parent of `DataTable`), and `Form`, but only `Form` currently uses `ComponentToolsTrait`. So:

- Add `use ComponentToolsTrait;` **and** `use InteractsWithNotifications;` to `src/Twig/Components/RecordActions.php` and `src/Twig/Components/AbstractRecordTable.php` (the two action-running hosts that lack `emit()`).
- In `InteractsWithActions`' success path (the `executeAction()` point where the handler returns without throwing), if the action carries a `getSuccessNotificationTitle()`, call `$this->notify(Notification::make()->title($title)->body($body)->success());`. Add `/** @method void notify(\Atrium\Notification\Notification $n) */` to the trait docblock so PHPStan accepts the host-provided method (the trait can't `use` the notifications trait itself without forcing `ComponentToolsTrait` on every consumer — keep it a documented host contract, mirroring how the trait already assumes host capabilities).
- Give built-in `DeleteAction`/`BulkDeleteAction` a `->successNotification('Deleted')` default so delete surfaces a toast.

> Add one functional assertion (`ActionNotificationTest`) that a delete row action emits `atrium:notification` — arrange via the existing DataTable/RecordActions functional test's mount block.

- [ ] **Step 6: Run the tests — expect pass**

Run: `vendor/bin/phpunit tests/Functional/FormNotificationTest.php`
Expected: PASS.

- [ ] **Step 7: Full gates + commit**

```bash
composer test && composer phpstan && composer cs
git add src/Twig/Components/Form.php templates/components/form.html.twig src/Action/Action.php src/Action/Concern/InteractsWithActions.php tests/Functional/
git commit -m "NTF-M3: raise real notifications from Form save + delete; Action::successNotification()"
```

Expected: whole suite green (fix any existing Form test that asserted the removed inline notice — update it to assert the notification instead).

---

# NTF-M4 — CSS, docs, dogfood, review

### Task 8: Safelist toast colour classes + rebuild CSS

The toast template composes `text-{scale}-500` / `dark:text-{scale}-400` (and `border`/`bg` chrome) in Twig. Conditionally-composed colour classes are purged unless safelisted (this exact class of bug hit the modals before).

**Files:**
- Modify: `assets/atrium.css`
- Regenerate: `assets/dist/atrium.css`

- [ ] **Step 1: Add `@source inline(...)` safelist**

In `assets/atrium.css`, alongside the existing `@source inline(...)` lines, add the toast colour utilities actually used by `notifications.html.twig`:

```css
@source inline("text-green-500 text-red-500 text-amber-500 text-sky-500 text-gray-500 text-primary-500");
@source inline("dark:text-green-400 dark:text-red-400 dark:text-amber-400 dark:text-sky-400 dark:text-gray-400 dark:text-primary-400");
```

(Only include scales the template can actually emit — green/red/amber/sky/gray/primary, matching the Twig `scale` map.)

- [ ] **Step 2: Rebuild the stylesheet** (verified procedure — `@import "tailwindcss"` needs the package resolvable)

```bash
npm install --no-save tailwindcss@4 @tailwindcss/cli@4
node_modules/.bin/tailwindcss -i assets/atrium.css -o assets/dist/atrium.css --minify
rm -rf node_modules package-lock.json
```

- [ ] **Step 3: Verify the classes are present**

Run: `grep -c 'text-green-500' assets/dist/atrium.css`
Expected: ≥ 1 (and similarly spot-check `text-red-500`, `dark:text-amber-400`).

- [ ] **Step 4: Commit**

```bash
git add assets/atrium.css assets/dist/atrium.css
git commit -m "NTF-M4: compile toast colour utilities"
```

---

### Task 9: Integration guide + CHANGELOG + PRD pointer

**Files:**
- Create: `docs/integration-guide/notifications/overview.md`
- Modify: `CHANGELOG.md`, `docs/PRDs/PRD.md`

- [ ] **Step 1: Write the guide** following the canonical template in `docs/integration-guide/README.md` (one-line summary, "When to use", copy-pasteable example for **both** channels, an API-reference table for `Notification` / `NotificationAction` / `NotificationStatus` / `InteractsWithNotifications` / `Notifier`, the live-vs-flash action rule, and the zero-consumer-JS asset note incl. the one `controllers.json` line). Model structure on an existing guide page (e.g. `docs/integration-guide/pages/view.md`).

- [ ] **Step 2: CHANGELOG** — add to `[Unreleased]`:
  - `### Added` — the Notifications subsystem (NTF-01/02): the `Notification` builder, two channels (live trait + `Notifier` flash bridge), in-toast link/emit actions, the shipped Stimulus controller (the bundle's first, auto-discovered — consumers add one `controllers.json` line), statuses/icons. Link the guide.
  - `### Changed` — **minor pre-1.0 BC:** the form's inline "saved successfully" notice is replaced by a success toast; delete actions now raise a toast.

- [ ] **Step 3: PRD pointer** — in `docs/PRDs/PRD.md` §10 Phase 6, note the notifications half delivered (NTF-01/02); NTF-03 (persisted) and the plugin system remain.

- [ ] **Step 4: Commit**

```bash
git add docs/ CHANGELOG.md
git commit -m "NTF-M4: notifications integration guide + CHANGELOG + PRD pointer"
```

---

### Task 10: Playground dogfood + browser-verify

**Files (in `../atrium-playground`):**
- Modify a resource to add a custom action that raises a toast; add an `Undo`-style emit listener on a component, and add the `@atriumphp/atrium` entry to the playground's `assets/controllers.json`.

- [ ] **Step 1:** Add the controller entry to `../atrium-playground/assets/controllers.json` (create the file if absent, mirroring Task 4's app entry).

- [ ] **Step 2:** On a playground resource (e.g. `ArticleResource`), add a custom row/header `Action` with `->successNotification('Article published', 'It is now live.')` (or a handler that calls `$this->notify(...)`), and a second notification carrying a `NotificationAction::make('Undo')->emit('article:undo', {id})`. Add a tiny Live Component (or extend an existing one) that listens for `article:undo` and flashes a confirmation, to prove the emit path.

- [ ] **Step 3:** Boot the playground and browser-verify with Playwright MCP:
  - trigger the action → a success toast slides in top-right, auto-dismisses after ~5s, pauses on hover;
  - trigger several quickly → they stack;
  - click an emit action → the `article:undo` listener fires;
  - save a form → a success toast appears (live), and a redirecting save shows the toast on the destination page (flash).
  - Capture a screenshot of a stacked toast.

- [ ] **Step 4:** If anything looks off (placement, contrast, dark mode), fix in `templates/components/notifications.html.twig` (+ re-run Task 8 if new classes appear) and re-verify.

- [ ] **Step 5:** Commit the playground demo (separate repo) and the atrium-side fixes if any.

```bash
# in ../atrium-playground
git add -A && git commit -m "Demo: notifications (toast + undo emit)"
```

---

### Task 11: Separate code-review agent

- [ ] **Step 1:** After all milestones are green, dispatch a fresh code-review agent over the notifications change set (the `src/Notification/**`, `src/Twig/Components/Notifications.php` + trait, the templates, the Stimulus controller + discovery wiring, and the Form/Action wiring). Focus: Live Component security (signed props, forge-resistance of `dismiss`/`runAction`), the live-vs-flash action rule, URL scheme-guarding of link actions, no Doctrine/sideways deps, PHPStan-max cleanliness, and the controllers.json/asset wiring correctness.

- [ ] **Step 2:** Triage findings; fix high-severity items; document accepted/deferred in this plan's "Review outcomes" section (append below).

- [ ] **Step 3:** Final gates: `composer test && composer phpstan && composer cs` — all green. Then finishing-a-development-branch.

---

## Verification (whole feature)

- `composer test && composer phpstan && composer cs` — green at every milestone and at the end.
- A Live Component raises a toast that appears with no reload; a controller/service flashes a toast that appears after a redirect; the form's old inline notice is gone.
- Toasts stack, auto-dismiss (or persist), pause on hover, close manually — consumer wrote no JS (one `controllers.json` line only).
- Link actions work in both channels; emit actions work on the live channel and are forge-resistant.
- Integration guide + CHANGELOG updated; playground dogfoods it; code-review clean.

## Out of scope (deferred, not dropped)

- **NTF-03** persisted/database notifications + topbar unread indicator — own cycle (needs a storage seam; collides with no-Doctrine-in-core).
- **Flex recipe** to auto-add the `controllers.json` entry — Phase 7 (packaging).
- **Closure-carrying toast actions** — intentionally excluded (non-serializable).
- **Raw-HTML notification bodies**, grouping/threading, non-UI channels (email/Slack).

## Review outcomes

_(Appended after Task 11.)_
