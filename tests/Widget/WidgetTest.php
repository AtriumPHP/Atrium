<?php

declare(strict_types=1);

namespace Atrium\Tests\Widget;

use Atrium\Widget\Stat;
use Atrium\Widget\StatsWidget;
use PHPUnit\Framework\TestCase;

final class WidgetTest extends TestCase
{
    public function testDefaults(): void
    {
        $widget = $this->statsWidget();

        self::assertTrue($widget->canView());
        self::assertNull($widget->getPollingInterval());
    }

    public function testWithParamsClonesAndDoesNotMutateOriginal(): void
    {
        $widget = new class extends StatsWidget {
            public function getStats(): array
            {
                $value = $this->params['v'] ?? 'base';

                return [Stat::make('V', \is_scalar($value) ? (string) $value : '')];
            }
        };

        $bound = $widget->withParams(['v' => 'ctx']);

        self::assertNotSame($widget, $bound);
        self::assertSame('ctx', $bound->getStats()[0]->getValue());
        // Original is untouched — two embeds cannot clobber each other.
        self::assertSame('base', $widget->getStats()[0]->getValue());
    }

    public function testStatsWidgetPicksStatsTemplate(): void
    {
        self::assertSame('@Atrium/components/widget/stats.html.twig', $this->statsWidget()->getView());
    }

    public function testStatsWidgetInnerGridClass(): void
    {
        $three = $this->statsWidget();
        self::assertSame('grid-cols-1 sm:grid-cols-2 lg:grid-cols-3', $three->getInnerGridClass());

        $two = new class extends StatsWidget {
            public function getStats(): array
            {
                return [];
            }

            public function getColumns(): int
            {
                return 2;
            }
        };
        self::assertSame('grid-cols-1 sm:grid-cols-2', $two->getInnerGridClass());
    }

    private function statsWidget(): StatsWidget
    {
        return new class extends StatsWidget {
            public function getStats(): array
            {
                return [Stat::make('A', '1')];
            }
        };
    }
}
