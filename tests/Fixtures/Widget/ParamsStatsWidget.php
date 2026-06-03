<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Widget;

use Atrium\Widget\Stat;
use Atrium\Widget\StatsWidget;

/**
 * Echoes the `range` embed param back as a stat value, proving scalar context
 * reaches the descriptor through {@see \Atrium\Widget\Widget::withParams()}.
 */
final class ParamsStatsWidget extends StatsWidget
{
    public function getStats(): array
    {
        $range = $this->params['range'] ?? 'none';

        return [Stat::make('Range', \is_scalar($range) ? (string) $range : 'none')];
    }
}
