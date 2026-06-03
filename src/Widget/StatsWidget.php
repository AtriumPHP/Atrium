<?php

declare(strict_types=1);

namespace Atrium\Widget;

/**
 * A widget that renders a row of {@see Stat} cards (WGT-09).
 *
 * Subclass it, inject your data source, and return the stats — the framework lays
 * them out in a responsive grid and makes the whole widget refreshable. The
 * widget spans the full grid width by default, since it is itself a row of cards.
 */
abstract class StatsWidget extends Widget
{
    /**
     * The stats to display, left to right.
     *
     * @return list<Stat>
     */
    abstract public function getStats(): array;

    /**
     * Columns for the inner stat grid at `lg`+ (one column on small screens, two
     * at `sm`+). Defaults to three.
     */
    public function getColumns(): int
    {
        return 3;
    }

    public function getColumnSpan(): int|string|array
    {
        return 'full';
    }

    public function getView(): string
    {
        return '@Atrium/components/widget/stats.html.twig';
    }

    /**
     * Tailwind grid classes for the inner stat layout.
     *
     * @internal
     */
    public function getInnerGridClass(): string
    {
        $columns = max(1, $this->getColumns());

        return match (true) {
            1 === $columns => 'grid-cols-1',
            2 === $columns => 'grid-cols-1 sm:grid-cols-2',
            default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-'.$columns,
        };
    }
}
