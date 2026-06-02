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

    /**
     * Read this column's value from a record and render it as a display string.
     */
    public function renderValue(object $record, PropertyAccessorInterface $accessor): string
    {
        $value = $accessor->isReadable($record, $this->name)
            ? $accessor->getValue($record, $this->name)
            : null;

        if (null !== $this->formatter) {
            return $this->stringify(($this->formatter)($value, $record));
        }

        return $this->stringify($value);
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
