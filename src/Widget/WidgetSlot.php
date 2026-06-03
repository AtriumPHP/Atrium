<?php

declare(strict_types=1);

namespace Atrium\Widget;

use Atrium\Layout\Component;
use Atrium\Layout\Concern\HasColumnSpan;
use Atrium\Layout\Concern\HasGrow;

/**
 * A widget placed in a layout tree (DSH-10).
 *
 * Layout containers ({@see \Atrium\Layout\Grid}, Section, …) hold
 * {@see Component}s, but a widget is a DI service referenced by class — so this
 * leaf adapts a widget *class* into the tree. It spans and grows exactly like a
 * field (default: one column — full-width in a stack, one cell in a `Grid`); its
 * template renders the independent `<twig:Atrium:Widget>` host, so per-widget
 * refresh, polling and authorization are preserved.
 *
 * Lives in the Widget package — it depends only on {@see Widget} (self) and the
 * foundational {@see Component} contract, so consumers in the panel layer
 * (dashboards, list screens) reference it downward.
 */
final class WidgetSlot implements Component
{
    use HasColumnSpan;
    use HasGrow;

    /**
     * Render-time params forwarded to the widget host (e.g. the resource-identity
     * context a list screen injects). Mutated only during resolution, on a
     * per-request descriptor.
     *
     * @var array<string, mixed>
     */
    private array $params = [];

    /**
     * @param class-string<Widget> $widgetClass
     */
    private function __construct(private readonly string $widgetClass)
    {
    }

    /**
     * @param class-string<Widget> $widgetClass
     */
    public static function make(string $widgetClass): self
    {
        if (!is_a($widgetClass, Widget::class, true)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a %s; a widget slot needs a widget class.', $widgetClass, Widget::class));
        }

        return new self($widgetClass);
    }

    public function getWidgetClass(): string
    {
        return $this->widgetClass;
    }

    /**
     * The params handed to the widget host when this slot renders.
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Merge resolution-time context (resource identity, routing) into the params
     * forwarded to the widget host. Called by
     * {@see WidgetLayoutConfiguration::applyContext()} while resolving a screen;
     * not part of the authoring surface.
     *
     * @param array<string, mixed> $params
     *
     * @internal
     */
    public function withContext(array $params): static
    {
        $this->params = [...$this->params, ...$params];

        return $this;
    }

    public function getChildComponents(): array
    {
        return [];
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/widget/slot.html.twig';
    }
}
