<?php

declare(strict_types=1);

namespace Atrium\Table;

use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * A fluent, immutable-by-convention description of a table column.
 *
 * Part of the public API contract (PRD §9). Doctrine-agnostic by design — the
 * data layer maps the column `name` to a query field, and the column knows how
 * to read and format a value from an arbitrary record object (TBL-07).
 */
final class Column
{
    private ?string $label = null;

    private bool $sortable = false;

    private bool $searchable = false;

    private bool $visible = true;

    /** @var 'left'|'center'|'right' */
    private string $alignment = 'left';

    private ?string $width = null;

    private bool $boolean = false;

    private bool $badge = false;

    /** @var string|(\Closure(mixed, object): string)|null */
    private string|\Closure|null $color = null;

    /** @var (\Closure(mixed, object): mixed)|null */
    private ?\Closure $formatter = null;

    private function __construct(
        private readonly string $name,
    ) {
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Hide the column outright (e.g. behind a permission computed at config time).
     * A hidden column is dropped from the table — header, cells, search and sort.
     */
    public function visible(bool $visible = true): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function hidden(bool $hidden = true): self
    {
        $this->visible = !$hidden;

        return $this;
    }

    /**
     * Horizontal alignment of the column: `left` (default), `center` or `right`.
     */
    public function alignment(string $alignment): self
    {
        $this->alignment = \in_array($alignment, ['center', 'right'], true) ? $alignment : 'left';

        return $this;
    }

    public function alignCenter(): self
    {
        return $this->alignment('center');
    }

    public function alignRight(): self
    {
        return $this->alignment('right');
    }

    /**
     * A fixed column width as a CSS length (e.g. `'8rem'`, `'120px'`), applied as
     * an inline style on the header cell.
     */
    public function width(string $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Render the value as a check/cross icon rather than the textual "Yes"/"No".
     */
    public function boolean(bool $boolean = true): self
    {
        $this->boolean = $boolean;

        return $this;
    }

    /**
     * Render the value as a coloured badge pill.
     */
    public function badge(bool $badge = true): self
    {
        $this->badge = $badge;

        return $this;
    }

    /**
     * Semantic colour for a {@see badge()} column: a fixed key (gray, primary,
     * red, green, amber, sky) or a callback of the value and record returning one.
     *
     * @param string|\Closure(mixed, object): string $color
     */
    public function color(string|\Closure $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Override how the column's value is rendered. The callback receives the raw
     * value and the whole record, and returns whatever should be displayed.
     *
     * @param callable(mixed, object): mixed $formatter
     */
    public function formatStateUsing(callable $formatter): self
    {
        $this->formatter = $formatter(...);

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? ucfirst(trim(preg_replace('/(?<!^)[A-Z]/', ' $0', $this->name) ?? $this->name));
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    /**
     * @return 'left'|'center'|'right'
     */
    public function getAlignment(): string
    {
        return $this->alignment;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function isBoolean(): bool
    {
        return $this->boolean;
    }

    public function isBadge(): bool
    {
        return $this->badge;
    }

    /**
     * Read this column's value from a record and render it as a display string.
     */
    public function renderValue(object $record, PropertyAccessorInterface $accessor): string
    {
        return $this->readAndFormat($record, $accessor)[1];
    }

    /**
     * A render-ready cell descriptor: the display string plus presentation hints
     * (alignment, and whether/how to render it as a boolean icon or a badge).
     *
     * @return array{value: string, align: 'left'|'center'|'right', boolean: bool, state: bool, badge: bool, color: string}
     */
    public function toCell(object $record, PropertyAccessorInterface $accessor): array
    {
        [$raw, $value] = $this->readAndFormat($record, $accessor);

        return [
            'value' => $value,
            'align' => $this->alignment,
            'boolean' => $this->boolean,
            'state' => (bool) $raw,
            'badge' => $this->badge,
            'color' => $this->resolveColor($raw, $record),
        ];
    }

    /**
     * @return array{0: mixed, 1: string}
     */
    private function readAndFormat(object $record, PropertyAccessorInterface $accessor): array
    {
        $raw = $accessor->isReadable($record, $this->name)
            ? $accessor->getValue($record, $this->name)
            : null;

        $display = null !== $this->formatter ? ($this->formatter)($raw, $record) : $raw;

        return [$raw, $this->stringify($display)];
    }

    private function resolveColor(mixed $value, object $record): string
    {
        if (null === $this->color) {
            return 'gray';
        }

        if ($this->color instanceof \Closure) {
            $resolved = ($this->color)($value, $record);

            return '' !== $resolved ? $resolved : 'gray';
        }

        return $this->color;
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            null === $value => '',
            \is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i'),
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \UnitEnum => $value->name,
            \is_array($value) => implode(', ', array_map($this->stringify(...), $value)),
            \is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }
}
