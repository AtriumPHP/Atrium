<?php

declare(strict_types=1);

namespace Atrium\Layout;

use Atrium\Layout\Concern\HasColumnSpan;
use Atrium\Layout\Concern\HasGrow;
use Atrium\Layout\Concern\HasVisibility;

/**
 * Base class for layout containers (Grid, Flex, Section, Fieldset).
 *
 * A layout component arranges child components into a responsive CSS grid. It is
 * pure presentation: it carries no view state of its own. Custom containers can
 * extend this and ship their own template, exactly like custom fields.
 */
abstract class LayoutComponent implements Component
{
    use HasColumnSpan;
    use HasGrow;
    use HasVisibility;

    /** @var list<Component> */
    protected array $components = [];

    /**
     * Column count for this container's own grid: an int (applied at `lg`+) or a
     * per-breakpoint map, e.g. `['md' => 2, 'xl' => 4]`.
     *
     * @var int|array<string, int>
     */
    protected int|array $columns = 1;

    /**
     * @param list<Component> $components
     */
    public function schema(array $components): static
    {
        $this->components = array_values($components);

        return $this;
    }

    /**
     * @param int|array<string, int> $columns
     */
    public function columns(int|array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    public function getChildComponents(): array
    {
        return $this->components;
    }

    /**
     * Tailwind grid-template-columns classes for this container.
     */
    public function getGridClass(): string
    {
        if (\is_int($this->columns)) {
            return 1 >= $this->columns ? 'grid-cols-1' : 'grid-cols-1 lg:grid-cols-'.$this->columns;
        }

        $classes = ['grid-cols-1'];
        foreach ($this->columns as $breakpoint => $count) {
            $classes[] = $breakpoint.':grid-cols-'.$count;
        }

        return implode(' ', $classes);
    }
}
