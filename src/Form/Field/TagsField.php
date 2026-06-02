<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

use Atrium\Form\Concern\HasPlaceholder;

/**
 * A list of string tags. Edited server-side as a set of add/remove rows (one
 * text input per tag); the form state and the model value are both a
 * `list<string>`. Zero JavaScript beyond the Live Component round-trip.
 */
class TagsField extends Field implements RepeatableField
{
    use HasPlaceholder;

    public function getType(): string
    {
        return 'tags';
    }

    public function normalize(mixed $value): mixed
    {
        return $this->parseTags($value);
    }

    public function toFormValue(mixed $value): mixed
    {
        // The form state is the ordered list of rows; empties are kept so the
        // user can edit them and dropped only on normalize (save).
        $rows = [];
        foreach ($this->toRows($value) as $tag) {
            $rows[] = $tag;
        }

        return $rows;
    }

    public function newRow(): mixed
    {
        return '';
    }

    public function rows(mixed $state): array
    {
        return $this->toRows($state);
    }

    /**
     * Coerce any supported shape (row list, model list, comma string) into a
     * list of tag strings, preserving order and any in-progress empty rows.
     *
     * @return list<string>
     */
    private function toRows(mixed $value): array
    {
        $parts = match (true) {
            \is_array($value) => $value,
            \is_scalar($value) => explode(',', (string) $value),
            default => [],
        };

        $rows = [];
        foreach ($parts as $part) {
            $rows[] = \is_scalar($part) ? trim((string) $part) : '';
        }

        return $rows;
    }

    /**
     * Parse a form value (comma string or array) into a clean tag list.
     *
     * @return list<string>
     */
    public function parseTags(mixed $value): array
    {
        $parts = \is_array($value)
            ? $value
            : explode(',', \is_scalar($value) ? (string) $value : '');

        $tags = [];
        foreach ($parts as $part) {
            $tag = \is_scalar($part) ? trim((string) $part) : '';
            if ('' !== $tag) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }
}
