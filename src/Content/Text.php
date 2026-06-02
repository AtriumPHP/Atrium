<?php

declare(strict_types=1);

namespace Atrium\Content;

/**
 * A block of static text — instructions, descriptions, callouts. Supports a
 * semantic colour, typographic size/weight, and an optional badge (pill) style.
 * Pass `html(true)` to render trusted markup as-is.
 */
final class Text extends ContentComponent
{
    /**
     * Semantic colour => Tailwind colour scale.
     *
     * @var array<string, string>
     */
    private const COLORS = [
        'gray' => 'gray',
        'info' => 'sky',
        'success' => 'green',
        'warning' => 'amber',
        'danger' => 'red',
        'primary' => 'indigo',
    ];

    private string $color = 'gray';

    private ?string $weight = null;

    private string $size = 'sm';

    private bool $badge = false;

    private bool $html = false;

    public function __construct(
        private readonly string|\Stringable $content,
    ) {
    }

    public static function make(string|\Stringable $content): self
    {
        return new self($content);
    }

    /**
     * One of: gray, info, success, warning, danger, primary.
     */
    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * One of: normal, medium, semibold, bold.
     */
    public function weight(string $weight): self
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * One of: sm, base, lg, xl.
     */
    public function size(string $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function badge(bool $badge = true): self
    {
        $this->badge = $badge;

        return $this;
    }

    public function html(bool $html = true): self
    {
        $this->html = $html;

        return $this;
    }

    public function getContent(): string
    {
        return (string) $this->content;
    }

    public function isHtml(): bool
    {
        return $this->html;
    }

    public function isBadge(): bool
    {
        return $this->badge;
    }

    /**
     * Size + weight classes.
     */
    public function getTypographyClass(): string
    {
        $size = \in_array($this->size, ['sm', 'base', 'lg', 'xl'], true) ? $this->size : 'sm';
        $classes = ['text-'.$size];

        if (null !== $this->weight && \in_array($this->weight, ['normal', 'medium', 'semibold', 'bold'], true)) {
            $classes[] = 'font-'.$this->weight;
        } elseif ($this->badge) {
            $classes[] = 'font-medium';
        }

        return implode(' ', $classes);
    }

    /**
     * Colour classes — badge fill or plain text colour, with dark-mode variants.
     */
    public function getColorClass(): string
    {
        $base = self::COLORS[$this->color] ?? 'gray';

        if ($this->badge) {
            return \sprintf('bg-%1$s-50 text-%1$s-700 dark:bg-%1$s-950 dark:text-%1$s-300', $base);
        }

        if ('gray' === $base) {
            return 'text-gray-700 dark:text-gray-300';
        }

        return \sprintf('text-%1$s-600 dark:text-%1$s-400', $base);
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/content/text.html.twig';
    }
}
