<?php

declare(strict_types=1);

namespace Atrium\Form\Concern;

/**
 * Placeholder text for an input-like field (FRM-13).
 */
trait HasPlaceholder
{
    private ?string $placeholder = null;

    public function placeholder(?string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }
}
