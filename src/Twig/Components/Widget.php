<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Widget\ChartWidget;
use Atrium\Widget\Widget as WidgetDefinition;
use Atrium\Widget\WidgetRegistry;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The generic reactive host for any widget (WGT-04).
 *
 * It takes a widget's class name as a non-writable (HMAC-checksummed, therefore
 * unforgeable) LiveProp, resolves the descriptor from the {@see WidgetRegistry},
 * enforces its authorization, and renders it. Each widget refreshes independently
 * — via the manual {@see refresh()} action or polling — without touching the rest
 * of the page.
 *
 * Embed it anywhere: `<twig:Atrium:Widget widget="App\Widget\RevenueStat" />`,
 * optionally with `:params="{ range: 30 }"` scalar context.
 */
#[AsLiveComponent(name: 'Atrium:Widget', template: '@Atrium/components/widget.html.twig')]
final class Widget
{
    use DefaultActionTrait;

    /**
     * Fully-qualified widget descriptor class. Non-writable → checksummed, so a
     * client cannot swap it to instantiate another class.
     */
    #[LiveProp]
    public string $widget = '';

    /**
     * Scalar embed context. Non-writable → checksummed, so it cannot be tampered
     * with from the client.
     *
     * @var array<string, scalar|array<array-key, scalar|null>|null>
     */
    #[LiveProp]
    public array $params = [];

    private bool $resolved = false;
    private ?WidgetDefinition $definition = null;

    public function __construct(
        private readonly WidgetRegistry $registry,
        private readonly ?ChartBuilderInterface $chartBuilder = null,
    ) {
    }

    /**
     * The resolved, context-bound descriptor — or null when the class is not a
     * registered widget (fails closed) or the viewer may not see it ({@see
     * WidgetDefinition::canView()}).
     */
    public function getDefinition(): ?WidgetDefinition
    {
        if ($this->resolved) {
            return $this->definition;
        }
        $this->resolved = true;

        $definition = $this->registry->find($this->widget);
        if (null === $definition) {
            return $this->definition = null;
        }

        $definition = $definition->withParams($this->params);

        return $this->definition = $definition->canView() ? $definition : null;
    }

    /**
     * The built Chart.js chart when the resolved widget is a {@see ChartWidget}
     * (and UX Chart.js is installed); null otherwise. Bound to the embed context,
     * since it is built from the resolved, params-bound descriptor.
     */
    public function getChart(): ?Chart
    {
        $definition = $this->getDefinition();
        if (!$definition instanceof ChartWidget || null === $this->chartBuilder) {
            return null;
        }

        return $definition->buildChart($this->chartBuilder);
    }

    /**
     * The `data-poll` directive driving auto-refresh, or null when the widget does
     * not poll. Each tick re-renders the component, recomputing the widget's data.
     */
    public function getPollDirective(): ?string
    {
        $interval = $this->getDefinition()?->getPollingInterval();
        if (null === $interval) {
            return null;
        }

        $milliseconds = $this->intervalToMilliseconds($interval);

        return $milliseconds > 0 ? \sprintf('delay(%d)|$render', $milliseconds) : null;
    }

    /**
     * Explicit manual refresh (e.g. a "↻" button). A no-op beyond forcing the
     * re-render that recomputes the widget's data.
     */
    #[LiveAction]
    public function refresh(): void
    {
    }

    private function intervalToMilliseconds(string $interval): int
    {
        if (1 !== preg_match('/^(\d+)\s*(ms|s|m)?$/', trim($interval), $matches)) {
            return 0;
        }

        $value = (int) $matches[1];

        return match ($matches[2] ?? 's') {
            'ms' => $value,
            'm' => $value * 60000,
            default => $value * 1000,
        };
    }
}
