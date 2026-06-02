<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

use Atrium\Form\Concern\HasPlaceholder;

/**
 * A string => string map. Edited server-side as add/remove rows (a key input
 * and a value input per row); the model value is an `array<string, string>`.
 * The form state is an ordered `list<array{key: string, value: string}>` so
 * keys stay editable and rows reorder without key collisions. Zero JavaScript
 * beyond the Live Component round-trip.
 */
class KeyValueField extends Field implements RepeatableField
{
    use HasPlaceholder;

    public function getType(): string
    {
        return 'key_value';
    }

    public function normalize(mixed $value): mixed
    {
        return $this->toPairs($value);
    }

    public function toFormValue(mixed $value): mixed
    {
        return $this->rows($value);
    }

    public function newRow(): mixed
    {
        return ['key' => '', 'value' => ''];
    }

    /**
     * The ordered row list for rendering. Accepts the model map, the row-list
     * form state, or legacy `key: value` lines.
     *
     * @return list<array{key: string, value: string}>
     */
    public function rows(mixed $state): array
    {
        $rows = [];

        if (\is_array($state)) {
            foreach ($state as $key => $val) {
                if (\is_array($val) && (\array_key_exists('key', $val) || \array_key_exists('value', $val))) {
                    $rows[] = [
                        'key' => $this->asString($val['key'] ?? ''),
                        'value' => $this->asString($val['value'] ?? ''),
                    ];

                    continue;
                }

                $rows[] = ['key' => (string) $key, 'value' => $this->asString($val)];
            }

            return $rows;
        }

        if (!\is_scalar($state)) {
            return [];
        }

        foreach (preg_split('/\r\n|\r|\n/', (string) $state) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line || !str_contains($line, ':')) {
                continue;
            }
            [$key, $val] = explode(':', $line, 2);
            $rows[] = ['key' => trim($key), 'value' => trim($val)];
        }

        return $rows;
    }

    /**
     * Reduce any supported shape to a clean `key => value` map (model value),
     * dropping rows with an empty key and keeping the last write on collision.
     *
     * @return array<string, string>
     */
    public function toPairs(mixed $value): array
    {
        $pairs = [];
        foreach ($this->rows($value) as $row) {
            $key = trim($row['key']);
            if ('' !== $key) {
                $pairs[$key] = $row['value'];
            }
        }

        return $pairs;
    }

    private function asString(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
