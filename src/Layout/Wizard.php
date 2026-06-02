<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A multi-step container (SCH-10): one {@see Step} is shown at a time, advanced
 * with Next / Back and submitted on the last step.
 *
 * Like {@see Tabs}, the current step is **server state** on the host Live
 * Component, and every panel is rendered (inactive ones hidden) so navigating
 * keeps each step's in-progress input. Unlike tabs, advancing is gated: the host
 * validates the current step's fields before moving on, and only lets the header
 * jump *backwards* to a visited step.
 *
 * Has a stable {@see getId()} (derived from its steps, or set explicitly) used
 * to key that current-step state.
 */
final class Wizard extends LayoutComponent
{
    private ?string $id = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * The wizard steps. Also fixes this wizard's id (from the step set) so it
     * stays stable even if visibility filtering later drops a step.
     *
     * @param list<Step> $steps
     */
    public function steps(array $steps): self
    {
        $this->schema($steps);
        $this->id ??= 'wizard-'.substr(md5(implode('|', array_map(static fn (Step $step): string => $step->getId(), $steps))), 0, 8);

        return $this;
    }

    /**
     * Override the auto-derived id (only needed for two otherwise-identical
     * step sets on one schema).
     */
    public function id(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        if (null !== $this->id) {
            return $this->id;
        }

        return 'wizard-'.substr(md5(implode('|', array_map(static fn (Step $step): string => $step->getId(), $this->getSteps()))), 0, 8);
    }

    /**
     * @return list<Step>
     */
    public function getSteps(): array
    {
        return array_values(array_filter(
            $this->getChildComponents(),
            static fn (Component $component): bool => $component instanceof Step,
        ));
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/layout/wizard.html.twig';
    }
}
