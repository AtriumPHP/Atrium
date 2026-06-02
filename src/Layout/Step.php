<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A single step within a {@see Wizard}: a labelled panel of components with its
 * own column grid, an optional icon and description. Identified by a stable slug
 * derived from its label (used to key the current-step state).
 */
final class Step extends LayoutComponent
{
    private ?string $icon = null;

    private ?string $description = null;

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

    public function description(?string $description): self
    {
        $this->description = $description;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Stable identifier for this step, derived from its label.
     */
    public function getId(): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $this->label), '-'));

        return '' === $slug ? 'step' : $slug;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/layout/step.html.twig';
    }
}
