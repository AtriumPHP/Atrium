<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Widget;

use Atrium\Widget\Stat;
use Atrium\Widget\StatsWidget;

/**
 * A stats widget whose first stat is a process-static render counter, used to
 * prove that a refresh re-runs {@see getStats()} (the kernel reboots between Live
 * Component interactions, so the counter must be static to survive).
 */
final class CounterStatsWidget extends StatsWidget
{
    public static int $renders = 0;

    public function getStats(): array
    {
        ++self::$renders;

        return [
            Stat::make('Renders', self::$renders)
                ->description('+1 each render')
                ->descriptionIcon('check')
                ->color('success'),
            Stat::make('Status', 'ok'),
        ];
    }
}
