<?php

declare(strict_types=1);

namespace Atrium\Tests\Widget;

use Atrium\Widget\Stat;
use Atrium\Widget\StatsWidget;
use Atrium\Widget\Widget;
use PHPUnit\Framework\TestCase;

final class WidgetTest extends TestCase
{
    public function testDefaults(): void
    {
        $widget = $this->intWidget(1);

        self::assertTrue($widget->canView());
        self::assertNull($widget->getPollingInterval());
    }

    public function testColumnSpanClassForInt(): void
    {
        self::assertSame('lg:col-span-2', $this->intWidget(2)->getColumnSpanClass());
    }

    public function testColumnSpanClassForFull(): void
    {
        $widget = new class extends Widget {
            public function getColumnSpan(): string
            {
                return 'full';
            }

            public function getView(): string
            {
                return 'x';
            }
        };

        self::assertSame('col-span-full', $widget->getColumnSpanClass());
    }

    public function testColumnSpanClassForResponsiveMap(): void
    {
        $widget = new class extends Widget {
            /** @return array<string, int> */
            public function getColumnSpan(): array
            {
                return ['md' => 2, 'xl' => 3];
            }

            public function getView(): string
            {
                return 'x';
            }
        };

        self::assertSame('md:col-span-2 xl:col-span-3', $widget->getColumnSpanClass());
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

    public function testStatsWidgetSpansFullAndPicksStatsTemplate(): void
    {
        // A plain StatsWidget that does not override getColumnSpan().
        $widget = new class extends StatsWidget {
            public function getStats(): array
            {
                return [Stat::make('A', '1')];
            }
        };

        self::assertSame('@Atrium/components/widget/stats.html.twig', $widget->getView());
        self::assertSame('col-span-full', $widget->getColumnSpanClass());
    }

    public function testStatsWidgetInnerGridClass(): void
    {
        $three = new class extends StatsWidget {
            public function getStats(): array
            {
                return [];
            }
        };
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

    private function intWidget(int $span): StatsWidget
    {
        return new class($span) extends StatsWidget {
            public function __construct(private readonly int $span = 1)
            {
            }

            public function getStats(): array
            {
                return [Stat::make('A', '1')];
            }

            public function getColumnSpan(): int
            {
                return $this->span;
            }
        };
    }
}
