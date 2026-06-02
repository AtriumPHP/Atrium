<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A bare responsive grid: arranges its children into columns with no heading or
 * border. `Grid::make(2)` is two columns at `lg`+ (one on smaller screens); pass
 * a per-breakpoint map for finer control.
 */
final class Grid extends LayoutComponent
{
    /**
     * @param int|array<string, int> $columns
     */
    public static function make(int|array $columns = 2): self
    {
        $grid = new self();
        $grid->columns = $columns;

        return $grid;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/layout/grid.html.twig';
    }
}
