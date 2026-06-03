<?php

declare(strict_types=1);

namespace Atrium\View;

/**
 * Renders a one-dimensional array / JSON map as a **key → value table** — for a
 * stored metadata bag, settings hash, or decoded JSON column. The state must be an
 * associative array; a JSON string is decoded automatically. An empty/non-map
 * state shows the `placeholder()`.
 *
 * ```php
 * KeyValueEntry::make('meta')->keyLabel('Field')->valueLabel('Value');
 * ```
 */
final class KeyValueEntry extends Entry
{
    private string $keyLabel = 'Key';
    private string $valueLabel = 'Value';

    public function keyLabel(string $label): static
    {
        $this->keyLabel = $label;

        return $this;
    }

    public function valueLabel(string $label): static
    {
        $this->valueLabel = $label;

        return $this;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/view/key_value.html.twig';
    }

    protected function viewExtras(mixed $state, object $record): array
    {
        $rows = $this->toRows($this->applyFormatter($state, $record));

        return [
            'rows' => $rows,
            'isEmpty' => [] === $rows,
            'keyLabel' => $this->keyLabel,
            'valueLabel' => $this->valueLabel,
        ];
    }

    /**
     * Normalise the state into a list of `{key, value}` string pairs.
     *
     * @return list<array{key: string, value: string}>
     */
    private function toRows(mixed $state): array
    {
        if (\is_string($state) && '' !== trim($state)) {
            $decoded = json_decode($state, true);
            $state = \is_array($decoded) ? $decoded : [];
        }

        if (!\is_array($state)) {
            return [];
        }

        $rows = [];
        foreach ($state as $key => $value) {
            $rows[] = ['key' => (string) $key, 'value' => self::stringify($value)];
        }

        return $rows;
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            null === $value => '',
            \is_bool($value) => $value ? 'Yes' : 'No',
            \is_scalar($value), $value instanceof \Stringable => (string) $value,
            \is_array($value) => json_encode($value) ?: '',
            default => '',
        };
    }
}
