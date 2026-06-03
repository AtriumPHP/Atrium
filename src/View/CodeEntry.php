<?php

declare(strict_types=1);

namespace Atrium\View;

/**
 * Renders the record value as a **monospace code block** — for a stored snippet,
 * a config blob, a JSON/SQL payload, or an identifier. The value is always
 * escaped (never executed); an optional {@see language()} label is shown for
 * context. An empty state shows the `placeholder()`.
 *
 * ```php
 * CodeEntry::make('payload')->language('json')->copyable();
 * ```
 */
final class CodeEntry extends Entry
{
    private ?string $language = null;
    private bool $copyable = false;
    private ?string $copyMessage = null;

    public function language(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function copyable(bool $copyable = true, ?string $message = null): static
    {
        $this->copyable = $copyable;
        $this->copyMessage = $message;

        return $this;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/view/code.html.twig';
    }

    protected function viewExtras(mixed $state, object $record): array
    {
        $code = self::stringify($this->applyFormatter($state, $record));

        return [
            'code' => $code,
            'isEmpty' => '' === $code,
            'language' => $this->language,
            'copyable' => $this->copyable,
            'copyValue' => $code,
            'copyMessage' => $this->copyMessage,
        ];
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            null === $value => '',
            \is_bool($value) => $value ? 'true' : 'false',
            \is_scalar($value), $value instanceof \Stringable => (string) $value,
            \is_array($value) => json_encode($value, \JSON_PRETTY_PRINT) ?: '',
            default => '',
        };
    }
}
