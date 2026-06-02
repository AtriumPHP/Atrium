<?php

declare(strict_types=1);

namespace Atrium\Table\Filter;

/**
 * A dropdown filter: choosing an option narrows the table to rows whose field
 * equals that option's value. The empty choice ("All") clears it.
 */
final class SelectFilter extends Filter
{
    /** @var array<string, string> */
    private array $options = [];

    private string $placeholder = 'All';

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * @param array<string, string> $options value => label
     */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Label for the empty (no-filter) choice.
     */
    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function conditions(string $value): array
    {
        if ('' === $value || !\array_key_exists($value, $this->options)) {
            return [];
        }

        return [$this->getField() => $value];
    }

    public function toView(string $value): array
    {
        $options = [['value' => '', 'label' => $this->placeholder]];
        foreach ($this->options as $optionValue => $label) {
            $options[] = ['value' => $optionValue, 'label' => $label];
        }

        return [
            'template' => $this->getTemplate(),
            'name' => $this->name,
            'label' => $this->getLabel(),
            'value' => \array_key_exists($value, $this->options) ? $value : '',
            'options' => $options,
        ];
    }
}
