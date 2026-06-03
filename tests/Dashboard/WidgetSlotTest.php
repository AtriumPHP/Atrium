<?php

declare(strict_types=1);

namespace Atrium\Tests\Dashboard;

use Atrium\Dashboard\WidgetSlot;
use Atrium\Layout\Component;
use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use PHPUnit\Framework\TestCase;

final class WidgetSlotTest extends TestCase
{
    public function testWrapsAWidgetClassAsALayoutComponent(): void
    {
        $slot = WidgetSlot::make(CounterStatsWidget::class);

        self::assertInstanceOf(Component::class, $slot);
        self::assertSame(CounterStatsWidget::class, $slot->getWidgetClass());
        self::assertSame([], $slot->getChildComponents());
        self::assertSame('@Atrium/components/widget/slot.html.twig', $slot->getTemplate());
    }

    public function testRejectsANonWidgetClass(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        WidgetSlot::make(\stdClass::class); // @phpstan-ignore argument.type
    }

    public function testSpansLikeAField(): void
    {
        // Default: one column (empty span class — full-width in a stack).
        self::assertSame('', WidgetSlot::make(CounterStatsWidget::class)->getColumnSpanClass());
        self::assertSame('lg:col-span-1', WidgetSlot::make(CounterStatsWidget::class)->columnSpan(1)->getColumnSpanClass());
        self::assertSame('col-span-full', WidgetSlot::make(CounterStatsWidget::class)->columnSpanFull()->getColumnSpanClass());
    }
}
