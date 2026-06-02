<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

use Atrium\Form\Concern\HasPlaceholder;

/**
 * A string => string map. For a zero-JS, server-driven experience it is edited
 * as `key: value` lines and shown as a small preview; the model value is an
 * `array<string, string>`. A richer row-based editor is a future enhancement.
 */
class KeyValueField extends Field
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
        if (!\is_array($value)) {
            return \is_scalar($value) ? (string) $value : '';
        }

        $lines = [];
        foreach ($value as $key => $val) {
            $lines[] = $key.': '.(\is_scalar($val) ? (string) $val : '');
        }

        return implode("\n", $lines);
    }

    /**
     * Parse a form value (key: value lines, or an existing map) into a clean map.
     *
     * @return array<string, string>
     */
    public function toPairs(mixed $value): array
    {
        if (\is_array($value)) {
            $pairs = [];
            foreach ($value as $key => $val) {
                $pairs[(string) $key] = \is_scalar($val) ? (string) $val : '';
            }

            return $pairs;
        }

        if (!\is_scalar($value)) {
            return [];
        }

        $pairs = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $value) ?: [] as $line) {
            $line = trim($line);
            if ('' === $line || !str_contains($line, ':')) {
                continue;
            }

            [$key, $val] = explode(':', $line, 2);
            $key = trim($key);
            if ('' !== $key) {
                $pairs[$key] = trim($val);
            }
        }

        return $pairs;
    }
}
