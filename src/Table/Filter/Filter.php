<?php

declare(strict_types=1);

namespace Atrium\Table\Filter;

/**
 * Base class for table filters — a labelled control shown in the filter bar that
 * narrows the query to an equality condition on a trusted field.
 *
 * A filter is stateless: its current value lives in the table component (a
 * `filterValues[name]` entry) and is passed back in to resolve {@see conditions()}
 * (the field => value equalities to apply) and {@see toView()} (the render
 * descriptor). Subclasses provide the control type; the default template renders
 * a `<select>`. Mark a subclass's `getTemplate()` to ship a custom control.
 *
 * Part of the public API contract (PRD §9) — treat changes as BC-relevant.
 */
abstract class Filter
{
    protected ?string $label = null;

    protected ?string $field = null;

    protected function __construct(
        protected readonly string $name,
    ) {
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Override the record field this filter applies to (defaults to the name).
     */
    public function attribute(string $field): static
    {
        $this->field = $field;

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

    public function getField(): string
    {
        return $this->field ?? $this->name;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/filter.html.twig';
    }

    /**
     * The equality conditions this filter contributes for its current value, or
     * an empty array when it is inactive (e.g. "All").
     *
     * @return array<string, scalar|bool|null>
     */
    abstract public function conditions(string $value): array;

    /**
     * A render-ready descriptor of the control for its current value.
     *
     * @return array{template: string, name: string, label: string, value: string, options: list<array{value: string, label: string}>}
     */
    abstract public function toView(string $value): array;
}
