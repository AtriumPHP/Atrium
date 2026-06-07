<?php

declare(strict_types=1);

namespace Atrium\Notification;

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;

/**
 * Raises a notification on the **flash channel**: it queues the notification in
 * the Symfony flash bag, so it appears after the next redirect (the
 * {@see \Atrium\Twig\Components\Notifications} host drains the bag on mount).
 * Inject it into controllers and services; for an in-place toast from a Live
 * Component, use {@see \Atrium\Twig\Components\Concern\InteractsWithNotifications}.
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

        if (!$session instanceof FlashBagAwareSessionInterface) {
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
