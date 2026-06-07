<?php

declare(strict_types=1);

namespace Atrium\Action\Concern;

use Atrium\Action\Action;
use Atrium\Notification\Notification;
use Atrium\Notification\Notifier;
use Symfony\Component\HttpFoundation\Response;
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
 *
 * @method void notify(\Atrium\Notification\Notification $notification)
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
    public function requestAction(#[LiveArg] string $name, #[LiveArg] string $id = ''): ?Response
    {
        $action = $this->findAction($name);
        if (null === $action || !$action->isServerAction() || !$this->canExecuteAction($action, $id)) {
            return null; // unknown / link-only / not applicable to this subject
        }

        if ($action->needsConfirmation()) {
            $this->confirmingAction = $name;
            $this->confirmingId = $id;

            return null;
        }

        return $this->executeAction($action, $id);
    }

    /**
     * Run the action the prompt is waiting on, then dismiss it. May return a
     * {@see Response} (e.g. a redirect after the subject was deleted).
     */
    #[LiveAction]
    public function confirmAction(): ?Response
    {
        $response = null;
        $action = null === $this->confirmingAction ? null : $this->findAction($this->confirmingAction);
        if (null !== $action && null !== $this->confirmingId
            && $action->isServerAction() && $this->canExecuteAction($action, $this->confirmingId)) {
            $response = $this->executeAction($action, $this->confirmingId);
        }

        $this->cancelAction();

        return $response;
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
     * Raise the action's declared success toast (if any) on the **live** channel
     * (no reload). Hosts call this from {@see executeAction()} once the handler has
     * run without throwing AND the host is staying in place, so the toast surfaces
     * only on a real success. Relies on the host's {@see InteractsWithNotifications::notify()}
     * (hence the trait's @method hint). When the action instead redirects, use
     * {@see flashActionSuccess()} so the toast survives the navigation.
     */
    protected function notifyActionSuccess(Action $action): void
    {
        $notification = $this->successNotification($action);
        if (null !== $notification) {
            $this->notify($notification);
        }
    }

    /**
     * Raise the action's declared success toast (if any) on the **flash** channel,
     * so it survives a redirect and surfaces on the destination page. Hosts call
     * this instead of {@see notifyActionSuccess()} when {@see executeAction()} is
     * about to return a redirect (e.g. a delete sends the user back to the list).
     */
    protected function flashActionSuccess(Action $action, Notifier $notifier): void
    {
        $notification = $this->successNotification($action);
        if (null !== $notification) {
            $notifier->send($notification);
        }
    }

    /**
     * Build the action's success {@see Notification}, or null when it declares none.
     */
    private function successNotification(Action $action): ?Notification
    {
        $title = $action->getSuccessNotificationTitle();
        if (null === $title) {
            return null;
        }

        return Notification::make()->title($title)->body($action->getSuccessNotificationBody())->success();
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
     * Run a server action against the subject identified by $subjectId. Returns a
     * {@see Response} to redirect (e.g. to the list after the subject was
     * deleted), or null to re-render in place.
     */
    abstract protected function executeAction(Action $action, string $subjectId): ?Response;
}
