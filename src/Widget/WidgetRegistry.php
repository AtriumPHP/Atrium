<?php

declare(strict_types=1);

namespace Atrium\Widget;

/**
 * The single lookup point for registered widgets.
 *
 * Widgets are collected via the `atrium.widget` tag (autoconfigured from
 * {@see Widget}) and indexed by class name. The host Live Component resolves
 * widgets *only* through this registry, so an unknown or forged class name fails
 * closed instead of instantiating arbitrary code.
 */
final class WidgetRegistry
{
    /** @var array<class-string<Widget>, Widget> */
    private array $byClass = [];

    /**
     * @param iterable<Widget> $widgets
     */
    public function __construct(iterable $widgets = [])
    {
        foreach ($widgets as $widget) {
            $this->byClass[$widget::class] = $widget;
        }
    }

    /**
     * The registered widget for the given class, or null when it is not a
     * registered widget (fails closed).
     */
    public function find(string $class): ?Widget
    {
        return $this->byClass[$class] ?? null;
    }

    public function has(string $class): bool
    {
        return isset($this->byClass[$class]);
    }

    /**
     * @return list<Widget>
     */
    public function all(): array
    {
        return array_values($this->byClass);
    }
}
