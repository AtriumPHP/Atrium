<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A labelled, bordered group of components, defaulting to a two-column grid.
 * Call `contained(false)` to drop the border while keeping the label and grid.
 */
final class Fieldset extends LayoutComponent
{
    private string $label = '';

    private bool $contained = true;

    public static function make(string $label): self
    {
        $fieldset = new self();
        $fieldset->label = $label;
        $fieldset->columns = 2;

        return $fieldset;
    }

    public function contained(bool $contained = true): self
    {
        $this->contained = $contained;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function isContained(): bool
    {
        return $this->contained;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/layout/fieldset.html.twig';
    }
}
