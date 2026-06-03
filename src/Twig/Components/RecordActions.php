<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;
use Atrium\Action\Concern\InteractsWithActions;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\Page\PageContext;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * A minimal Live Component that hosts one record's header actions on an otherwise
 * **static** screen — the read-only View screen, whose Delete button must still run
 * server-side. It carries no view state of its own; it reuses the shared
 * {@see InteractsWithActions} confirm → run → cancel plumbing so a
 * {@see \Atrium\Table\Action\DeleteAction} runs (and redirects to the list) exactly
 * as it does on the edit screen — no view-specific reactive logic, no client JS.
 *
 * Link actions (Edit / View) render as plain anchors; only server actions reach
 * the action plumbing. Mirrors the action-hosting parts of {@see Form}.
 *
 * @internal
 */
#[AsLiveComponent(name: 'Atrium:RecordActions', template: '@Atrium/components/record_actions.html.twig')]
final class RecordActions
{
    use DefaultActionTrait;
    use InteractsWithActions;

    #[LiveProp]
    public string $resource = '';

    #[LiveProp]
    public ?string $entityId = null;

    #[LiveProp]
    public string $pathPrefix = '';

    /** The page operation whose header actions to host (the View screen). */
    #[LiveProp]
    public string $operation = 'view';

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
        private readonly DataWriterInterface $writer,
    ) {
    }

    public function mount(string $resource, ?string $entityId = null, string $pathPrefix = '', string $operation = 'view'): void
    {
        $this->resource = $resource;
        $this->entityId = $entityId;
        $this->pathPrefix = $pathPrefix;
        $this->operation = $operation;
    }

    public function hasHeaderActions(): bool
    {
        return [] !== $this->getHeaderActionViews();
    }

    /**
     * Render-ready descriptors for the screen's visible header actions, gated for
     * this record.
     *
     * @return list<array<string, mixed>>
     */
    public function getHeaderActionViews(): array
    {
        $context = new ActionContext($this->pathPrefix, $this->resource, $this->entityId ?? '');

        $views = [];
        foreach ($this->headerActions() as $action) {
            if ($action->isVisible() && $this->standaloneAuthorized($action)) {
                $views[] = $action->toStandaloneView($context);
            }
        }

        return $views;
    }

    /**
     * @return list<Action>
     */
    private function headerActions(): array
    {
        return $this->resourceObject()->resolveHeaderActions($this->operation, $this->pageContext());
    }

    protected function findAction(string $name): ?Action
    {
        foreach ($this->headerActions() as $action) {
            if ($action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    protected function canExecuteAction(Action $action, string $subjectId): bool
    {
        $record = $this->loadEntity();

        return null !== $record && $action->isVisibleFor($record) && $this->actionAuthorized($action, $record);
    }

    protected function executeAction(Action $action, string $subjectId): ?Response
    {
        $handler = $action->getHandler();
        $record = $this->loadEntity();
        if (null === $handler || null === $record
            || !$action->isVisibleFor($record) || !$this->actionAuthorized($action, $record)) {
            return null;
        }

        $resource = $this->resourceObject();
        $deletes = 'delete' === $action->getAbility();
        $name = $action->getName();
        // Run atomically: the lifecycle hooks and the handler commit together —
        // the same contract the table and form action hosts honour.
        $this->writer->transactional(function () use ($resource, $handler, $record, $deletes, $name): void {
            $resource->beforeAction($name, $record);
            if ($deletes) {
                $resource->beforeDelete($record);
            }
            $handler($record, $this->writer);
            if ($deletes) {
                $resource->afterDelete($record);
            }
            $resource->afterAction($name, $record);
        });

        // The record is gone (e.g. deleted) — there is nothing left to view, so
        // return to the list; otherwise re-render the actions in place.
        if (null === $this->loadEntity()) {
            return new RedirectResponse($this->pageContext()->indexUrl());
        }

        return null;
    }

    /**
     * Authorize an action for rendering against the loaded record; an action with
     * no ability is always allowed.
     */
    private function standaloneAuthorized(Action $action): bool
    {
        $ability = $action->getAbility();

        return null === $ability || $this->resourceObject()->can($ability, $this->loadEntity());
    }

    private function actionAuthorized(Action $action, ?object $record): bool
    {
        $ability = $action->getAbility();

        return null === $ability || $this->resourceObject()->can($ability, $record);
    }

    private function pageContext(): PageContext
    {
        $resource = $this->resourceObject();

        return new PageContext(
            $this->resource,
            $this->pathPrefix,
            $this->entityId,
            $resource->getSingularLabel(),
            $resource->getLabel(),
        );
    }

    private function resourceObject(): AdminResource
    {
        return $this->registry->getBySlug($this->resource);
    }

    private function loadEntity(): ?object
    {
        if (null === $this->entityId) {
            return null;
        }

        // Resolve within the resource's scope: an id outside scopeQuery() is not
        // reachable even when posted straight to the component endpoint.
        return $this->dataProvider->find(
            $this->resourceObject()->getEntityClass(),
            $this->entityId,
            $this->resourceObject()->scopeFilters(),
        );
    }
}
