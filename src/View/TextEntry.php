<?php

declare(strict_types=1);

namespace Atrium\View;

use Atrium\View\Concern\ResolvesColor;

/**
 * The workhorse read-only entry: renders a record value as text, with optional
 * formatting (money, dates, numbers), truncation, a badge/colour treatment, a
 * copy button, and list rendering for array/multi-value state.
 *
 * Shares the semantic colour palette with {@see \Atrium\Content\Text} and
 * {@see \Atrium\Table\Column} (`gray`/`info`/`success`/`warning`/`danger`/`primary`)
 * so badges read identically across the panel.
 */
final class TextEntry extends Entry
{
    use ResolvesColor;

    private bool $badge = false;
    private string|\Closure|null $color = null;

    private ?string $money = null;
    private int $moneyDivideBy = 1;
    private ?string $dateFormat = null;
    private bool $since = false;
    private ?int $decimals = null;

    private ?int $limit = null;
    private ?int $words = null;
    private ?int $lineClamp = null;
    private string $prefix = '';
    private string $suffix = '';

    private bool $html = false;
    private bool $copyable = false;
    private ?string $copyMessage = null;

    private bool $asList = false;
    private bool $bulleted = false;
    private ?string $separator = null;

    private string $size = 'sm';
    private ?string $weight = null;
    private bool $mono = false;

    public function badge(bool $badge = true): static
    {
        $this->badge = $badge;

        return $this;
    }

    public function color(string|\Closure $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function money(string $currency, int $divideBy = 1): static
    {
        $this->money = $currency;
        $this->moneyDivideBy = max(1, $divideBy);

        return $this;
    }

    public function dateTime(string $format = 'Y-m-d H:i'): static
    {
        $this->dateFormat = $format;

        return $this;
    }

    public function date(string $format = 'Y-m-d'): static
    {
        $this->dateFormat = $format;

        return $this;
    }

    public function since(bool $since = true): static
    {
        $this->since = $since;

        return $this;
    }

    public function numeric(int $decimals = 0): static
    {
        $this->decimals = $decimals;

        return $this;
    }

    public function limit(int $characters): static
    {
        $this->limit = $characters;

        return $this;
    }

    public function words(int $words): static
    {
        $this->words = $words;

        return $this;
    }

    public function lineClamp(int $lines): static
    {
        $this->lineClamp = $lines;

        return $this;
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    public function html(bool $html = true): static
    {
        $this->html = $html;

        return $this;
    }

    public function copyable(bool $copyable = true, ?string $message = null): static
    {
        $this->copyable = $copyable;
        $this->copyMessage = $message;

        return $this;
    }

    public function listWithLineBreaks(bool $list = true): static
    {
        $this->asList = $list;

        return $this;
    }

    public function bulleted(bool $bulleted = true): static
    {
        $this->bulleted = $bulleted;
        $this->asList = $this->asList || $bulleted;

        return $this;
    }

    public function separator(string $separator): static
    {
        $this->separator = $separator;

        return $this;
    }

    /**
     * One of: sm, base, lg, xl.
     */
    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * One of: normal, medium, semibold, bold.
     */
    public function weight(string $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function fontFamily(string $family): static
    {
        $this->mono = 'mono' === $family;

        return $this;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/view/text.html.twig';
    }

    protected function viewExtras(mixed $state, object $record): array
    {
        $formatted = $this->applyFormatter($state, $record);
        $items = $this->asList ? array_map($this->formatScalar(...), $this->toItems($formatted)) : null;
        $value = null === $items ? $this->decorate($this->formatScalar($formatted)) : '';

        return [
            'value' => $value,
            'items' => $items,
            'bulleted' => $this->bulleted,
            'isEmpty' => null === $items ? ('' === $value) : ([] === $items),
            'isHtml' => $this->html,
            'isBadge' => $this->badge,
            'colorClass' => $this->colorClass($state, $record),
            'copyable' => $this->copyable,
            'copyValue' => $this->stringify($formatted),
            'copyMessage' => $this->copyMessage,
            'typographyClass' => $this->typographyClass(),
        ];
    }

    /**
     * Decorate the scalar display with prefix/suffix (lists decorate per item is
     * not applied; affixes wrap the whole value).
     */
    private function decorate(string $value): string
    {
        return '' === $value ? '' : $this->prefix.$value.$this->suffix;
    }

    /**
     * Format a single scalar state into its display string, applying money / date
     * / numeric formatting and limit/words truncation.
     */
    private function formatScalar(mixed $value): string
    {
        if (null !== $this->money && is_numeric($value)) {
            return $this->formatMoney((float) $value);
        }

        if (($this->since || null !== $this->dateFormat) && $value instanceof \DateTimeInterface) {
            return $this->since ? $this->relativeTime($value) : $value->format($this->dateFormat ?? 'Y-m-d H:i');
        }

        if (null !== $this->decimals && is_numeric($value)) {
            return number_format((float) $value, $this->decimals);
        }

        return $this->truncate($this->stringify($value));
    }

    private function truncate(string $value): string
    {
        if (null !== $this->limit && mb_strlen($value) > $this->limit) {
            return rtrim(mb_substr($value, 0, $this->limit)).'…';
        }

        if (null !== $this->words) {
            $parts = preg_split('/\s+/', trim($value)) ?: [];
            if (\count($parts) > $this->words) {
                return implode(' ', \array_slice($parts, 0, $this->words)).'…';
            }
        }

        return $value;
    }

    private function formatMoney(float $amount): string
    {
        $amount /= $this->moneyDivideBy;

        if (class_exists(\NumberFormatter::class)) {
            $formatter = new \NumberFormatter('en', \NumberFormatter::CURRENCY);

            return $formatter->formatCurrency($amount, strtoupper($this->money ?? '')) ?: '';
        }

        return strtoupper($this->money ?? '').' '.number_format($amount, 2);
    }

    private function relativeTime(\DateTimeInterface $when): string
    {
        $diff = (new \DateTimeImmutable('@'.time()))->diff($when);
        $units = [
            [$diff->y, 'year'],
            [$diff->m, 'month'],
            [$diff->d, 'day'],
            [$diff->h, 'hour'],
            [$diff->i, 'minute'],
        ];

        foreach ($units as [$count, $unit]) {
            if ($count > 0) {
                $label = $count.' '.$unit.(1 === $count ? '' : 's');

                return 1 === $diff->invert ? $label.' ago' : 'in '.$label;
            }
        }

        return 'just now';
    }

    /**
     * Resolve list items from array state, or by splitting a string on the
     * configured separator.
     *
     * @return list<mixed>
     */
    private function toItems(mixed $state): array
    {
        if (\is_array($state)) {
            return array_values($state);
        }

        if (null !== $this->separator && '' !== $this->separator && \is_string($state) && '' !== $state) {
            return array_map('trim', explode($this->separator, $state));
        }

        return null === $state || '' === $state ? [] : [$state];
    }

    private function colorClass(mixed $state, object $record): string
    {
        $scale = $this->colorScale($this->color, $state, $record);

        if (!$this->badge) {
            return match (true) {
                null === $scale, 'gray' === $scale => 'text-gray-700 dark:text-gray-300',
                default => \sprintf('text-%1$s-600 dark:text-%1$s-400', $scale),
            };
        }

        $scale ??= 'gray';

        return \sprintf(
            'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-%1$s-50 text-%1$s-700 dark:bg-%1$s-950 dark:text-%1$s-300',
            $scale,
        );
    }

    private function typographyClass(): string
    {
        $size = \in_array($this->size, ['sm', 'base', 'lg', 'xl'], true) ? $this->size : 'sm';
        $classes = ['text-'.$size];

        if (null !== $this->weight && \in_array($this->weight, ['normal', 'medium', 'semibold', 'bold'], true)) {
            $classes[] = 'font-'.$this->weight;
        }

        if ($this->mono) {
            $classes[] = 'font-mono';
        }

        if (null !== $this->lineClamp) {
            $classes[] = 'line-clamp-'.$this->lineClamp;
        }

        return implode(' ', $classes);
    }

    private function stringify(mixed $value): string
    {
        return match (true) {
            null === $value => '',
            \is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof \DateTimeInterface => $value->format($this->dateFormat ?? 'Y-m-d H:i'),
            $value instanceof \BackedEnum => (string) $value->value,
            $value instanceof \UnitEnum => $value->name,
            \is_array($value) => implode(', ', array_map($this->stringify(...), $value)),
            \is_scalar($value), $value instanceof \Stringable => (string) $value,
            default => '',
        };
    }
}
