<?php

declare(strict_types=1);

namespace Atrium\Tests\Widget;

use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use Atrium\Tests\Fixtures\Widget\HiddenWidget;
use Atrium\Widget\WidgetRegistry;
use PHPUnit\Framework\TestCase;

final class WidgetRegistryTest extends TestCase
{
    public function testIndexesByClass(): void
    {
        $counter = new CounterStatsWidget();
        $hidden = new HiddenWidget();
        $registry = new WidgetRegistry([$counter, $hidden]);

        self::assertTrue($registry->has(CounterStatsWidget::class));
        self::assertSame($counter, $registry->find(CounterStatsWidget::class));
        self::assertSame($hidden, $registry->find(HiddenWidget::class));
        self::assertSame([$counter, $hidden], $registry->all());
    }

    public function testFindFailsClosedForUnknownClass(): void
    {
        $registry = new WidgetRegistry([new CounterStatsWidget()]);

        self::assertFalse($registry->has('App\\Nope'));
        self::assertNull($registry->find('App\\Nope'));
    }

    public function testEmptyRegistry(): void
    {
        self::assertSame([], (new WidgetRegistry())->all());
    }
}
