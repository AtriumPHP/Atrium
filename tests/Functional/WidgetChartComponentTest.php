<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Tests\Fixtures\Widget\SalesChartWidget;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class WidgetChartComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testChartWidgetRendersCanvasWithHeading(): void
    {
        $component = $this->createLiveComponent('Atrium:Widget', ['widget' => SalesChartWidget::class]);
        $html = $component->render()->toString();

        self::assertStringContainsString('Sales per month', $html);
        self::assertStringContainsString('<canvas', $html);
        // UX Chart.js mounts its Stimulus controller and serialises the chart view.
        self::assertStringContainsString('symfony--ux-chartjs--chart', $html);
        self::assertStringContainsString('Sales', $html);
    }
}
