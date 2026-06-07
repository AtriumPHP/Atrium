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
 * @phpstan-type NotificationActionLinkArray array{kind: 'link', label: string, icon: ?string, color: ?string, closeOnClick: bool, url: ?string}
 * @phpstan-type NotificationActionEmitArray array{kind: 'emit', label: string, icon: ?string, color: ?string, closeOnClick: bool, event: string, payload: array<string, scalar|null>}
 * @phpstan-type NotificationActionArray NotificationActionLinkArray|NotificationActionEmitArray
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
     * @return NotificationActionLinkArray|NotificationActionEmitArray
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
                // Scheme-guarded in PHP so the template prints `action.url` directly.
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
     * @param NotificationActionLinkArray|NotificationActionEmitArray $data
     */
    public static function fromArray(array $data): self
    {
        $action = self::make($data['label'])
            ->icon($data['icon'] ?? null)
            ->color($data['color'] ?? null)
            ->closeOnClick($data['closeOnClick']);

        if ('link' === $data['kind']) {
            // A guarded-away url may serialize to null; reconstruct as a dead anchor.
            return $action->url(\is_string($data['url']) ? $data['url'] : '#');
        }

        return $action->emit($data['event'], $data['payload']);
    }

    /**
     * Guard a URL before it reaches an `href`: reject any explicit scheme other
     * than http(s)/mailto/tel (e.g. `javascript:`, `data:`); relative/anchor/query
     * paths pass. Same policy as {@see \Atrium\Action\Action::safeUrl()} (kept
     * local to avoid a sideways dep).
     */
    private static function safeUrl(?string $url): ?string
    {
        if (null === $url || '' === trim($url)) {
            return null;
        }

        $candidate = ltrim($url);
        if (preg_match('#^(?:https?:|mailto:|tel:|/|\#|\?|\.)#i', $candidate)) {
            return $url;
        }

        return preg_match('#^[a-z][a-z0-9+.\-]*:#i', $candidate) ? null : $url;
    }
}
