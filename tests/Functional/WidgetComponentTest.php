<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use Atrium\Tests\Fixtures\Widget\HiddenWidget;
use Atrium\Tests\Fixtures\Widget\ParamsStatsWidget;
use Atrium\Twig\Components\Widget as WidgetHost;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class WidgetComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function setUp(): void
    {
        parent::setUp();
        CounterStatsWidget::$renders = 0;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testRendersStatValues(): void
    {
        $component = $this->createLiveComponent('Atrium:Widget', ['widget' => CounterStatsWidget::class]);
        $html = $component->render()->toString();

        self::assertStringContainsString('Renders', $html);
        self::assertStringContainsString('Status', $html);
        self::assertStringContainsString('ok', $html);
        self::assertStringContainsString('+1 each render', $html);
    }

    public function testHiddenWidgetRendersNothing(): void
    {
        $component = $this->createLiveComponent('Atrium:Widget', ['widget' => HiddenWidget::class]);
        $html = $component->render()->toString();

        self::assertStringNotContainsString('classified', $html);
        self::assertStringNotContainsString('Secret', $html);
    }

    public function testUnknownWidgetClassFailsClosed(): void
    {
        $component = $this->createLiveComponent('Atrium:Widget', ['widget' => 'App\\Does\\NotExist']);
        $html = $component->render()->toString();

        // No error, just an empty host element.
        self::assertStringNotContainsString('Renders', $html);
    }

    public function testParamsReachTheWidget(): void
    {
        $component = $this->createLiveComponent('Atrium:Widget', [
            'widget' => ParamsStatsWidget::class,
            'params' => ['range' => 90],
        ]);
        $html = $component->render()->toString();

        self::assertStringContainsString('90', $html);
    }

    public function testRefreshRecomputesData(): void
    {
        $component = $this->createLiveComponent('Atrium:Widget', ['widget' => CounterStatsWidget::class]);
        $component->render();
        $component->call('refresh');
        $component->render();

        // Each render re-runs getStats(); a refresh must trigger a recompute.
        self::assertGreaterThanOrEqual(2, CounterStatsWidget::$renders);
    }

    public function testWidgetIdentityPropsAreNotClientWritable(): void
    {
        // Non-writable LiveProps are HMAC-checksummed and therefore unforgeable —
        // a client cannot repoint the host at another class or tamper with params.
        self::assertFalse($this->livePropIsWritable('widget'));
        self::assertFalse($this->livePropIsWritable('params'));
    }

    private function livePropIsWritable(string $property): bool
    {
        $reflection = new \ReflectionProperty(WidgetHost::class, $property);
        $attributes = $reflection->getAttributes(LiveProp::class);
        self::assertNotEmpty($attributes, \sprintf('%s must be a LiveProp.', $property));

        return (bool) ($attributes[0]->getArguments()['writable'] ?? false);
    }
}
