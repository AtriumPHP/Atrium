<?php

declare(strict_types=1);

namespace Atrium\Dashboard;

use Atrium\Layout\Component;
use Atrium\Widget\Widget;

/**
 * The layout of a {@see Dashboard} — the dashboard analogue of the form
 * {@see \Atrium\Form\Schema} (DSH-09).
 *
 * Configure it from {@see Dashboard::dashboard()}: the simple path is
 * {@see widgets()} (a flat list of widget classes); richer dashboards use
 * {@see schema()} to nest {@see WidgetSlot}s inside layout containers (Grid,
 * Section, Fieldset, Flex). There is no top-level `columns()` — multiple columns
 * come from `Grid::make(N)`, exactly as in a form.
 *
 * Pure configuration — reusable and unit-testable in isolation.
 */
final class DashboardConfiguration
{
    /** @var list<Component> */
    private array $components = [];

    /**
     * Flat convenience: render these widgets as a stack, wrapping each class in a
     * full-width {@see WidgetSlot}. Mirrors {@see \Atrium\Form\Schema::fields()}.
     *
     * @param list<class-string<Widget>> $widgetClasses
     */
    public function widgets(array $widgetClasses): self
    {
        $this->components = array_map(
            static fn (string $class): WidgetSlot => WidgetSlot::make($class),
            array_values($widgetClasses),
        );

        return $this;
    }

    /**
     * A mixed tree of {@see WidgetSlot}s and layout containers. The same verb the
     * layout containers use (`Grid::make(2)->schema([...])`), so nesting reads
     * uniformly. Mirrors {@see \Atrium\Form\Schema::components()}.
     *
     * @param list<Component> $components
     */
    public function schema(array $components): self
    {
        $this->components = array_values($components);

        return $this;
    }

    /**
     * Top-level nodes, for rendering.
     *
     * @return list<Component>
     */
    public function getComponents(): array
    {
        return $this->components;
    }
}
