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
        if (
            !isset($data['id'], $data['title'], $data['status'])
            || !\is_string($data['id'])
            || !\is_string($data['title'])
            || !\is_string($data['status'])
        ) {
            throw new \InvalidArgumentException('Malformed notification payload.');
        }

        $status = NotificationStatus::tryFrom($data['status']) ?? NotificationStatus::Neutral;

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
            $duration = $data['duration'];
            $notification->duration = null === $duration ? null : (int) (is_numeric($duration) ? $duration : 0);
        }
        if (isset($data['actions']) && \is_array($data['actions'])) {
            $notification->actions(array_map(
                static fn (mixed $a): NotificationAction => NotificationAction::fromArray(self::validateActionArray($a)),
                array_values($data['actions']),
            ));
        }

        return $notification;
    }

    /**
     * Runtime guard that validates and reconstructs an action element from a deserialized
     * payload before handing it to {@see NotificationAction::fromArray()}. By extracting
     * each field with an explicit type-check and rebuilding the shape from scratch, PHPStan
     * can infer the correct return type without any inline annotation or suppression.
     *
     * @return NotificationActionArray
     */
    private static function validateActionArray(mixed $a): array
    {
        if (
            !\is_array($a)
            || !isset($a['kind'], $a['label'])
            || !\is_string($a['kind'])
            || !\is_string($a['label'])
            || !\in_array($a['kind'], ['link', 'emit'], true)
        ) {
            throw new \InvalidArgumentException('Each notification action must be a valid action array.');
        }

        $icon = isset($a['icon']) && \is_string($a['icon']) ? $a['icon'] : null;
        $color = isset($a['color']) && \is_string($a['color']) ? $a['color'] : null;
        $closeOnClick = isset($a['closeOnClick']) && \is_bool($a['closeOnClick']) ? $a['closeOnClick'] : true;

        if ('link' === $a['kind']) {
            return [
                'kind' => 'link',
                'label' => $a['label'],
                'icon' => $icon,
                'color' => $color,
                'closeOnClick' => $closeOnClick,
                'url' => isset($a['url']) && \is_string($a['url']) ? $a['url'] : null,
            ];
        }

        $event = isset($a['event']) && \is_string($a['event']) ? $a['event'] : '';
        $rawPayload = isset($a['payload']) && \is_array($a['payload']) ? $a['payload'] : [];
        /** @var array<string, scalar|null> $payload */
        $payload = array_filter(
            array_combine(
                array_map(static fn (mixed $k): string => (string) $k, array_keys($rawPayload)),
                array_values($rawPayload),
            ),
            static fn (mixed $v): bool => null === $v || \is_scalar($v),
        );

        return [
            'kind' => 'emit',
            'label' => $a['label'],
            'icon' => $icon,
            'color' => $color,
            'closeOnClick' => $closeOnClick,
            'event' => $event,
            'payload' => $payload,
        ];
    }
}
