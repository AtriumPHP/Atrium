<?php

declare(strict_types=1);

namespace Atrium\Dashboard;

use Atrium\Layout\Component;
use Atrium\Layout\Concern\HasColumnSpan;
use Atrium\Layout\Concern\HasGrow;
use Atrium\Widget\Widget;

/**
 * A widget placed in a dashboard's layout tree (DSH-10).
 *
 * Layout containers ({@see \Atrium\Layout\Grid}, Section, …) hold
 * {@see Component}s, but a widget is a DI service referenced by class — so this
 * leaf adapts a widget *class* into the tree. It spans and grows exactly like a
 * field (default: one column — full-width in a stack, one cell in a `Grid`); its
 * template renders the independent `<twig:Atrium:Widget>` host, so per-widget
 * refresh, polling and authorization are preserved.
 *
 * Lives in the Dashboard namespace (a panel-layer consumer of both Layout and
 * Widget) to keep the bridge a downward dependency, never a sideways one.
 */
final class WidgetSlot implements Component
{
    use HasColumnSpan;
    use HasGrow;

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

    public function getChildComponents(): array
    {
        return [];
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/widget/slot.html.twig';
    }
}
