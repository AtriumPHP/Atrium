<?php

declare(strict_types=1);

namespace Atrium\View\Concern;

/**
 * The shared semantic-colour vocabulary for read-only entries — the same palette
 * used by {@see \Atrium\Content\Text} and {@see \Atrium\Table\Column}
 * (`gray`/`info`/`success`/`warning`/`danger`/`primary`), so a status badge reads
 * identically whether it appears in a table cell, a content block or a view entry.
 *
 * A colour may be a static name or a record-aware closure `fn ($state, $record)`.
 *
 * @internal
 */
trait ResolvesColor
{
    /**
     * Semantic colour => Tailwind colour scale.
     *
     * @var array<string, string>
     */
    private const COLOR_SCALES = [
        'gray' => 'gray',
        'info' => 'sky',
        'success' => 'green',
        'warning' => 'amber',
        'danger' => 'red',
        'primary' => 'primary',
    ];

    /**
     * Resolve a colour option to its Tailwind scale (e.g. `success` => `green`),
     * or null when unset/unknown.
     */
    private function colorScale(string|\Closure|null $color, mixed $state, object $record): ?string
    {
        if ($color instanceof \Closure) {
            $resolved = $color($state, $record);
            $name = \is_string($resolved) ? $resolved : '';
        } else {
            $name = $color ?? '';
        }

        return self::COLOR_SCALES[$name] ?? null;
    }
}
