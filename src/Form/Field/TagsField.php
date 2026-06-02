<?php

declare(strict_types=1);

namespace Atrium\Form\Field;

use Atrium\Form\Concern\HasPlaceholder;

/**
 * A list of string tags. For a zero-JS, server-driven experience the value is
 * edited as a comma-separated string and shown as chips; the model value is a
 * `list<string>`. A richer chip-input UI is a future enhancement.
 */
class TagsField extends Field
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
        if (\is_array($value)) {
            return implode(', ', $this->parseTags($value));
        }

        return \is_scalar($value) ? (string) $value : '';
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
