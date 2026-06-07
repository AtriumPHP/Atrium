<?php

declare(strict_types=1);

namespace Atrium\Notification;

/**
 * Raises a notification on the flash channel (the send() method lands in NTF-M3).
 * The flash-bag key lives here in the notification layer so nothing below depends
 * upward on the Twig component (CLAUDE.md rule #2).
 *
 * NTF-M2 stub: only the {@see FLASH_KEY} constant exists for now, so the
 * {@see \Atrium\Twig\Components\Notifications} host can drain the flash bag. The
 * constructor + send() arrive in NTF-M3 (Task 6).
 */
final class Notifier
{
    public const FLASH_KEY = 'atrium.notifications';
}
