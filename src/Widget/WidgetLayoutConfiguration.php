<?php

declare(strict_types=1);

namespace Atrium\Widget;

use Atrium\Layout\Component;

/**
 * A layout of widgets — {@see WidgetSlot}s composed with layout containers
 * ({@see \Atrium\Layout\Grid}, Section, Fieldset, Flex), the widget analogue of
 * a form {@see \Atrium\Form\Schema}.
 *
 * The shared base of the dashboard and list-screen widget configurations: the
 * simple path is {@see widgets()} (a flat list of widget classes); richer layouts
 * use {@see schema()} to nest {@see WidgetSlot}s inside containers. There is no
 * top-level `columns()` — multiple columns come from `Grid::make(N)`, exactly as
 * in a form. Subclasses add no behaviour; they exist so the *intent* reads
 * correctly at the call site (a dashboard vs. a list screen).
 *
 * Pure configuration — reusable and unit-testable in isolation.
 */
abstract class WidgetLayoutConfiguration
{
    /** @var list<Component> */
    private array $components = [];

    /**
     * Flat convenience: render these widgets as a stack, wrapping each class in a
     * full-width {@see WidgetSlot}. Mirrors {@see \Atrium\Form\Schema::fields()}.
     *
     * @param list<class-string<Widget>> $widgetClasses
     */
    public function widgets(array $widgetClasses): static
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
    public function schema(array $components): static
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

    /**
     * Forward resolution-time context (resource slug, path prefix, labels) to
     * every {@see WidgetSlot} in the tree — including those nested inside layout
     * containers — so a widget can scope its data or build URLs without the
     * integrator wiring it. Mutates the per-request descriptor; not authoring API.
     *
     * @param array<string, mixed> $params
     *
     * @internal
     */
    public function applyContext(array $params): static
    {
        $this->applyContextTo($this->components, $params);

        return $this;
    }

    /**
     * @param list<Component>      $components
     * @param array<string, mixed> $params
     */
    private function applyContextTo(array $components, array $params): void
    {
        foreach ($components as $component) {
            if ($component instanceof WidgetSlot) {
                $component->withContext($params);
            }
            $this->applyContextTo($component->getChildComponents(), $params);
        }
    }
}
