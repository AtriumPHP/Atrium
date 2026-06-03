<?php

declare(strict_types=1);

namespace Atrium\View;

use Atrium\Layout\Component;

/**
 * Repeats a **nested schema** once per item of a relation / array attribute — for
 * an order's line items, a record's addresses, a list of JSON rows. The state must
 * be a list (a Doctrine collection, an array of entities, or an array of maps);
 * each item becomes the **record** for the nested entries, so the same dotted-path
 * resolution and the full entry vocabulary apply inside the block.
 *
 * ```php
 * RepeatableEntry::make('items')->columns(3)->schema([
 *     TextEntry::make('name')->weight('semibold'),
 *     TextEntry::make('qty')->label('Qty'),
 *     TextEntry::make('price')->money('USD', divideBy: 100),
 * ]);
 * ```
 *
 * This is the one recursive entry: its template re-enters the shared layout
 * renderer for each item, so nested layout containers (and even nested
 * repeatables) compose naturally.
 */
final class RepeatableEntry extends Entry
{
    /** @var list<Component> */
    private array $schemaComponents = [];

    /** @var int|array<string, int> */
    private int|array $columns = 1;

    /** @var int|array<string, int> */
    private int|array $grid = 1;

    private bool $contained = true;

    /**
     * The entries rendered for each item.
     *
     * @param list<Component> $components
     */
    public function schema(array $components): static
    {
        $this->schemaComponents = array_values($components);

        return $this;
    }

    /**
     * Columns within each repeated block (the item's own schema grid).
     *
     * @param int|array<string, int> $columns
     */
    public function columns(int|array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Lay the repeated blocks out in a grid of this many columns (a gallery);
     * default is one block per row.
     *
     * @param int|array<string, int> $columns
     */
    public function grid(int|array $columns): static
    {
        $this->grid = $columns;

        return $this;
    }

    /**
     * Wrap each repeated block in a card (border + padding). On by default.
     */
    public function contained(bool $contained = true): static
    {
        $this->contained = $contained;

        return $this;
    }

    /**
     * @return list<Component>
     */
    public function getSchemaComponents(): array
    {
        return $this->schemaComponents;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/view/repeatable.html.twig';
    }

    /**
     * The render descriptor — everything the template needs in one place: the
     * resolved items, the nested schema to render for each, the two grid classes
     * (the gallery of blocks, and the columns within a block) and the card flag.
     */
    protected function viewExtras(mixed $state, object $record): array
    {
        $items = self::normaliseItems($this->applyFormatter($state, $record));

        return [
            'items' => $items,
            'isEmpty' => [] === $items,
            'schema' => $this->schemaComponents,
            // The blocks gallery engages at `sm` so `grid(n)` actually lays cards
            // out at normal widths; the columns within a block follow the layout
            // convention (`lg`), like a Section's own grid.
            'gridClass' => self::gridClass($this->grid, 'sm'),
            'columnsClass' => self::gridClass($this->columns, 'lg'),
            'contained' => $this->contained,
        ];
    }

    /**
     * Normalise the state into a list of record-like objects: pass objects through,
     * cast associative arrays to objects (so nested entries can read their keys by
     * name), and drop scalars (which cannot host a schema).
     *
     * @return list<object>
     */
    private static function normaliseItems(mixed $state): array
    {
        if ($state instanceof \Traversable) {
            $state = iterator_to_array($state);
        }

        if (!\is_array($state)) {
            return [];
        }

        $items = [];
        foreach ($state as $item) {
            if (\is_object($item)) {
                $items[] = $item;
            } elseif (\is_array($item)) {
                $items[] = (object) $item;
            }
        }

        return $items;
    }

    /**
     * Tailwind grid-template-columns classes, mirroring the layout containers. An
     * int count stacks to one column below `$breakpoint` and opens to N at it; a
     * per-breakpoint map is emitted verbatim (the caller controls the breakpoints).
     *
     * @param int|array<string, int> $columns
     */
    private static function gridClass(int|array $columns, string $breakpoint): string
    {
        if (\is_int($columns)) {
            return 1 >= $columns ? 'grid-cols-1' : 'grid-cols-1 '.$breakpoint.':grid-cols-'.$columns;
        }

        $classes = ['grid-cols-1'];
        foreach ($columns as $at => $count) {
            $classes[] = $at.':grid-cols-'.$count;
        }

        return implode(' ', $classes);
    }
}
