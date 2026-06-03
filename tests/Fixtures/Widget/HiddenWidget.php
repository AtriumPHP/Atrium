<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Widget;

use Atrium\Widget\Stat;
use Atrium\Widget\StatsWidget;

/**
 * A widget the viewer may never see — {@see canView()} returns false, so the host
 * must render nothing.
 */
final class HiddenWidget extends StatsWidget
{
    public function canView(): bool
    {
        return false;
    }

    public function getStats(): array
    {
        return [Stat::make('Secret', 'classified')];
    }
}
