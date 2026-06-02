<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A titled, bordered group of components with an optional description and its own
 * column grid. May be collapsible (rendered as a native `<details>`, so
 * collapsing needs no JavaScript) and/or compact (reduced padding).
 */
final class Section extends LayoutComponent
{
    private ?string $heading = null;

    private ?string $description = null;

    private bool $collapsible = false;

    private bool $collapsed = false;

    private bool $compact = false;

    public static function make(?string $heading = null): self
    {
        $section = new self();
        $section->heading = $heading;

        return $section;
    }

    public function description(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function collapsible(bool $collapsible = true): self
    {
        $this->collapsible = $collapsible;

        return $this;
    }

    /**
     * Render collapsed by default (implies collapsible).
     */
    public function collapsed(bool $collapsed = true): self
    {
        $this->collapsed = $collapsed;
        if ($collapsed) {
            $this->collapsible = true;
        }

        return $this;
    }

    public function compact(bool $compact = true): self
    {
        $this->compact = $compact;

        return $this;
    }

    public function getHeading(): ?string
    {
        return $this->heading;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isCollapsible(): bool
    {
        return $this->collapsible;
    }

    public function isCollapsed(): bool
    {
        return $this->collapsed;
    }

    public function isCompact(): bool
    {
        return $this->compact;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/layout/section.html.twig';
    }
}
