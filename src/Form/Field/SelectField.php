<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

/**
 * Dropdown select. Options can be static or computed from the current form
 * state via {@see optionsUsing()} — the basis for reactive dependent selects
 * (FRM-05) when combined with a live() parent field.
 */
final class SelectField extends Field
{
    /** @var array<string, string> */
    private array $options = [];

    /** @var (\Closure(array<string, mixed>): array<string, string>)|null */
    private ?\Closure $optionsCallback = null;

    private ?string $placeholder = '—';

    public function getType(): string
    {
        return 'select';
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
     * @param callable(array<string, mixed>): array<string, string> $callback
     */
    public function optionsUsing(callable $callback): self
    {
        $this->optionsCallback = $callback(...);

        return $this;
    }

    public function placeholder(?string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    /**
     * Resolve the available options, optionally for the current form state.
     *
     * @param array<string, mixed> $formData
     *
     * @return array<string, string> value => label
     */
    public function getOptions(array $formData = []): array
    {
        if (null !== $this->optionsCallback) {
            return ($this->optionsCallback)($formData);
        }

        return $this->options;
    }

    public function normalize(mixed $value): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return \is_scalar($value) ? (string) $value : null;
    }

    public function toFormValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return \is_scalar($value) ? (string) $value : '';
    }
}
