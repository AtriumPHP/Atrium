<?php

declare(strict_types=1);

namespace Atrium\Widget;

/**
 * One stat card in a {@see StatsWidget} — a label, a primary value, and optional
 * description, icon, colour and link.
 *
 * A fluent value builder in the same style as {@see \Atrium\Table\Column}.
 */
final class Stat
{
    private ?string $description = null;
    private ?string $descriptionIcon = null;
    private string $color = 'gray';
    private ?string $url = null;

    private function __construct(
        private readonly string $label,
        private readonly string $value,
    ) {
    }

    public static function make(string $label, string|int|float $value): self
    {
        return new self($label, (string) $value);
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function descriptionIcon(string $icon): self
    {
        $this->descriptionIcon = $icon;

        return $this;
    }

    /**
     * Semantic colour for the description line. Accepts a palette name (`primary`,
     * `gray`, `green`, `red`, `amber`, `sky`) or an intent alias (`success`,
     * `danger`, `warning`, `info`). Defaults to `gray`.
     */
    public function color(string $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Make the whole card a link to the given URL.
     */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDescriptionIcon(): ?string
    {
        return $this->descriptionIcon;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }
}
