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
