<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A single tab within a {@see Tabs} container: a labelled panel of components
 * with its own column grid. May carry an icon and a small badge. Identified by a
 * stable slug derived from its label (used to key the active-tab state).
 */
final class Tab extends LayoutComponent
{
    private ?string $icon = null;

    private int|string|null $badge = null;

    private function __construct(
        private readonly string $label,
    ) {
    }

    public static function make(string $label): self
    {
        return new self($label);
    }

    public function icon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function badge(int|string|null $badge): self
    {
        $this->badge = $badge;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getBadge(): int|string|null
    {
        return $this->badge;
    }

    /**
     * Stable identifier for this tab, derived from its label.
     */
    public function getId(): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $this->label), '-'));

        return '' === $slug ? 'tab' : $slug;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/layout/tab.html.twig';
    }
}
