<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Notification\Notification;
use Atrium\Notification\Notifier;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The single toast host, mounted once in the panel layout. Holds the active
 * notification stack as server state and renders it; the shipped Stimulus
 * controller handles only client-side timing/animation.
 *
 * Two intake paths: live (another component emits `atrium:notification` →
 * {@see receive()}); flash ({@see mount()} drains the Symfony flash bag).
 *
 * The stack is a non-writable LiveProp (HMAC-signed), so a client can never forge
 * or mutate notifications — only the server-side listener / flash drain append.
 */
#[AsLiveComponent(name: 'Atrium:Notifications', template: '@Atrium/components/notifications.html.twig')]
final class Notifications
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

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
            return;
        }

        if (!$session instanceof FlashBagAwareSessionInterface) {
            return;
        }

        $flashBag = $session->getFlashBag();
        if (!$session->isStarted() && !$flashBag->has(Notifier::FLASH_KEY)) {
            return;
        }

        /** @var list<array<string, mixed>> $flashed */
        $flashed = $flashBag->get(Notifier::FLASH_KEY);
        foreach ($flashed as $payload) {
            $this->append($payload);
        }
    }

    /**
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

    #[LiveAction]
    public function runAction(#[LiveArg] string $id, #[LiveArg] int $index): void
    {
        foreach ($this->notifications as $n) {
            if (($n['id'] ?? null) !== $id) {
                continue;
            }
            $actions = $n['actions'] ?? null;
            $action = \is_array($actions) ? ($actions[$index] ?? null) : null;
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
        try {
            $this->notifications[] = Notification::fromArray($payload)->toArray();
        } catch (\InvalidArgumentException) {
            // drop silently — server-authored payloads should never be malformed
        }
    }
}
