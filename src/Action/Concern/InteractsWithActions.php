<?php

declare(strict_types=1);

namespace Atrium\Action\Concern;

use Atrium\Action\Action;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * Adds confirmable, server-driven action handling to a Live Component.
 *
 * This concern owns the generic confirm → run → cancel flow and its state, so
 * any component that hosts actions (the table today; forms, pages and bulk
 * actions later) composes it instead of re-implementing it. The host supplies
 * only the two domain-specific pieces: how to {@see findAction()} by name and
 * how to {@see executeAction()} it against a subject id.
 *
 * The state and `#[LiveAction]` methods must live on the component itself (a UX
 * requirement); the trait is how we share them without bloating the host.
 */
trait InteractsWithActions
{
    /**
     * The action awaiting confirmation and the subject id it will run on — a
     * server-driven prompt, so confirmation needs no client JavaScript.
     */
    #[LiveProp]
    public ?string $confirmingAction = null;

    #[LiveProp]
    public ?string $confirmingId = null;

    /**
     * Trigger a server action by name: those needing confirmation open the
     * prompt; the rest run immediately. Link actions are plain anchors and never
     * reach here.
     */
    #[LiveAction]
    public function requestAction(#[LiveArg] string $name, #[LiveArg] string $id): void
    {
        $action = $this->findAction($name);
        if (null === $action || !$action->isServerAction() || !$this->canExecuteAction($action, $id)) {
            return; // unknown / link-only / not applicable to this subject
        }

        if ($action->needsConfirmation()) {
            $this->confirmingAction = $name;
            $this->confirmingId = $id;

            return;
        }

        $this->executeAction($action, $id);
    }

    /**
     * Run the action the prompt is waiting on, then dismiss it.
     */
    #[LiveAction]
    public function confirmAction(): void
    {
        $action = null === $this->confirmingAction ? null : $this->findAction($this->confirmingAction);
        if (null !== $action && null !== $this->confirmingId
            && $action->isServerAction() && $this->canExecuteAction($action, $this->confirmingId)) {
            $this->executeAction($action, $this->confirmingId);
        }

        $this->cancelAction();
    }

    /**
     * Dismiss the confirmation prompt without acting.
     */
    #[LiveAction]
    public function cancelAction(): void
    {
        $this->confirmingAction = null;
        $this->confirmingId = null;
    }

    public function isConfirming(): bool
    {
        return null !== $this->confirmingAction;
    }

    public function getConfirmingLabel(): string
    {
        return $this->confirmingActionInstance()?->getLabel() ?? 'Confirm';
    }

    public function getConfirmingMessage(): string
    {
        return $this->confirmingActionInstance()?->getConfirmationMessage() ?? '';
    }

    public function getConfirmingColor(): string
    {
        return $this->confirmingActionInstance()?->getColor() ?? 'primary';
    }

    private function confirmingActionInstance(): ?Action
    {
        return null === $this->confirmingAction ? null : $this->findAction($this->confirmingAction);
    }

    /**
     * Find a hosted action by name.
     */
    abstract protected function findAction(string $name): ?Action;

    /**
     * Whether the action may run against the subject — i.e. the subject exists
     * and the action is visible for it. Checked before showing a confirmation
     * prompt and again before running, so a crafted request cannot surface or
     * run a hidden action.
     */
    abstract protected function canExecuteAction(Action $action, string $subjectId): bool;

    /**
     * Run a server action against the subject identified by $subjectId.
     */
    abstract protected function executeAction(Action $action, string $subjectId): void;
}
