<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Form\Get;
use Atrium\Layout\Component;
use Atrium\Layout\Step;
use Atrium\Layout\Wizard;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * The reactive create/edit form for schemas that contain a {@see Wizard}
 * (SCH-10). It adds multi-step navigation on top of {@see Form}'s
 * hydrate/validate/save core without touching it: the current step is server
 * state, Next gates on the current step's validation, Back is free, and the
 * header may jump backwards. A resource opts in automatically via
 * {@see \Atrium\Resource\AdminResource::getFormComponentName()}.
 */
#[AsLiveComponent(name: 'Atrium:WizardForm', template: '@Atrium/components/wizard_form.html.twig')]
final class WizardForm extends Form
{
    /**
     * Current step index per {@see Wizard}, keyed by the wizard id. Server-held
     * so navigation — and gated advancement — needs no client JavaScript.
     *
     * @var array<string, int>
     */
    #[LiveProp]
    public array $currentSteps = [];

    /**
     * Validate the current step's fields and, if they pass, advance one step.
     * On failure the errors surface and the step stays put.
     */
    #[LiveAction]
    public function nextStep(#[LiveArg] string $wizard): void
    {
        $target = $this->findWizard($wizard);
        if (null === $target) {
            return;
        }

        $index = $this->currentStep($target);
        $step = $target->getSteps()[$index] ?? null;
        if (null === $step) {
            return;
        }

        [, $this->errors] = $this->collectErrors(
            $this->fieldsIn($step),
            new Get($this->formData),
            $this->operation(),
        );
        if ([] !== $this->errors) {
            return;
        }

        if ($index < \count($target->getSteps()) - 1) {
            $this->currentSteps[$target->getId()] = $index + 1;
        }
    }

    /**
     * Step back one step without validating.
     */
    #[LiveAction]
    public function previousStep(#[LiveArg] string $wizard): void
    {
        $target = $this->findWizard($wizard);
        if (null === $target) {
            return;
        }

        $this->errors = [];
        $index = $this->currentStep($target);
        if ($index > 0) {
            $this->currentSteps[$target->getId()] = $index - 1;
        }
    }

    /**
     * Jump to an already-visited (earlier) step via the header; advancing past
     * the current step still goes through {@see nextStep}.
     */
    #[LiveAction]
    public function gotoStep(#[LiveArg] string $wizard, #[LiveArg] int $step): void
    {
        $target = $this->findWizard($wizard);
        if (null === $target) {
            return;
        }

        if ($step >= 0 && $step < $this->currentStep($target)) {
            $this->errors = [];
            $this->currentSteps[$target->getId()] = $step;
        }
    }

    /**
     * The current step index of a wizard (clamped to a valid step). Used by the
     * wizard template.
     */
    public function currentStep(Wizard $wizard): int
    {
        $last = max(0, \count($wizard->getSteps()) - 1);

        return max(0, min($this->currentSteps[$wizard->getId()] ?? 0, $last));
    }

    /**
     * Also reveal the {@see Wizard} step holding an errored field after a failed
     * save (extends the base {@see Form} which handles {@see Tabs}).
     *
     * @param list<string> $erroredFields
     */
    protected function focusContainer(Component $component, array $erroredFields): void
    {
        parent::focusContainer($component, $erroredFields);

        if ($component instanceof Wizard) {
            foreach ($component->getSteps() as $index => $step) {
                if ([] !== array_intersect($this->fieldNamesIn($step), $erroredFields)) {
                    $this->currentSteps[$component->getId()] = $index;
                    break;
                }
            }
        }
    }

    /**
     * The first {@see Wizard} in the schema, or the one with the given id.
     */
    private function findWizard(?string $id): ?Wizard
    {
        return $this->findWizardIn($this->schema()->getComponents(), $id);
    }

    /**
     * @param list<Component> $components
     */
    private function findWizardIn(array $components, ?string $id): ?Wizard
    {
        foreach ($components as $component) {
            if ($component instanceof Wizard && (null === $id || $component->getId() === $id)) {
                return $component;
            }

            $nested = $this->findWizardIn($component->getChildComponents(), $id);
            if (null !== $nested) {
                return $nested;
            }
        }

        return null;
    }
}
