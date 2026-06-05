<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataQuery;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\DataProvider\RelationDataProvider;
use Atrium\Relation\Relation;
use Atrium\Relation\RelationDescriptor;
use Atrium\Relation\RelationResolver;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Atrium\Table\TableConfiguration;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;

/**
 * @internal a parent-scoped related-records table (REL-04). Extends the shared
 * {@see AbstractRecordTable} core; its rows come from the {@see RelationDataProvider}
 * for the resolved {@see RelationDescriptor} and the loaded parent record. M1 is
 * read-only (one-to-many); link/unlink actions arrive in later milestones.
 */
#[AsLiveComponent(name: 'Atrium:RelationManager', template: '@Atrium/components/relation_manager.html.twig')]
final class RelationManager extends AbstractRecordTable
{
    /** Parent resource slug. */
    #[LiveProp]
    public string $resource = '';

    #[LiveProp]
    public string $parentId = '';

    #[LiveProp]
    public string $relationName = '';

    /** The host screen — 'edit' (full) or 'view' (read-only); drives M2+ actions. */
    #[LiveProp]
    public string $screen = 'edit';

    private ?Relation $relation = null;
    private ?RelationDescriptor $descriptor = null;
    private ?AdminResource $targetResource = null;
    private ?object $parentRecord = null;

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
        private readonly RelationDataProvider $relationProvider,
        private readonly RelationResolver $resolver,
        DataWriterInterface $writer,
        PropertyAccessorInterface $accessor,
    ) {
        parent::__construct($writer, $accessor);
    }

    public function mount(string $resource, string $parentId, string $relation, string $pathPrefix = '', string $screen = 'edit'): void
    {
        $this->resource = $resource;
        $this->parentId = $parentId;
        $this->relationName = $relation;
        $this->pathPrefix = $pathPrefix;
        $this->screen = $screen;
        $this->perPage = $this->tableConfig()->getPerPage();
    }

    // -- AbstractRecordTable seams ----------------------------------------

    protected function resource(): AdminResource
    {
        return $this->target();
    }

    protected function entityClass(): string
    {
        return $this->descriptor()->childEntityClass;
    }

    protected function makeTableConfiguration(): TableConfiguration
    {
        $relation = $this->relation();
        $config = $this->target()->table(TableConfiguration::make());

        return $relation->hasTable() ? $relation->applyTable($config) : $config;
    }

    protected function fetchRecords(DataQuery $query): iterable
    {
        return $this->relationProvider->listRelated($this->descriptor(), $this->parent(), $query);
    }

    protected function countRecords(DataQuery $query): int
    {
        return $this->relationProvider->countRelated($this->descriptor(), $this->parent(), $query);
    }

    protected function findRecord(string $id): ?object
    {
        // Scoped find through the standard provider; parent-scope enforcement for
        // mutating actions arrives with those actions in M2/M3.
        return $this->dataProvider->find(
            $this->entityClass(),
            $id,
            $this->target()->scopeFilters(),
            $this->descriptor()->childIdField,
        );
    }

    /** @return list<Action> */
    public function getHeaderActions(): array
    {
        return [];   // link/create actions arrive in M2/M3
    }

    public function getRecordActions(): array
    {
        return [];   // read-only in M1
    }

    protected function actionContext(?string $id): ActionContext
    {
        return new ActionContext($this->pathPrefix, $this->target()->getSlug(), $id ?? '');
    }

    protected function rowUrl(object $record, ActionContext $context): ?string
    {
        return null;   // row-click target is wired with nesting (M4)
    }

    public function getEmptyHeading(): string
    {
        return $this->relation()->getEmptyHeading() ?? 'No '.strtolower($this->relation()->getLabel());
    }

    public function getEmptyDescription(): ?string
    {
        return $this->relation()->getEmptyDescription() ?? parent::getEmptyDescription();
    }

    public function getEmptyIcon(): ?string
    {
        return $this->relation()->getEmptyIcon() ?? parent::getEmptyIcon();
    }

    // -- Resolution helpers -----------------------------------------------

    private function parentResource(): AdminResource
    {
        return $this->registry->getBySlug($this->resource);
    }

    private function target(): AdminResource
    {
        return $this->targetResource ??= $this->resolver->targetResource($this->relation());
    }

    private function relation(): Relation
    {
        if (null !== $this->relation) {
            return $this->relation;
        }

        foreach ($this->parentResource()->relations() as $relation) {
            if ($relation->getName() === $this->relationName) {
                return $this->relation = $relation;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Resource "%s" has no relation "%s".', $this->resource, $this->relationName));
    }

    private function descriptor(): RelationDescriptor
    {
        return $this->descriptor ??= $this->resolver->resolve($this->relation(), $this->parentResource()->getIdentifierField());
    }

    private function parent(): object
    {
        return $this->parentRecord ??= $this->dataProvider->find(
            $this->parentResource()->getEntityClass(),
            $this->parentId,
            $this->parentResource()->scopeFilters(),
            $this->parentResource()->getIdentifierField(),
        ) ?? throw new \RuntimeException(\sprintf('Parent record "%s" not found for relation "%s".', $this->parentId, $this->relationName));
    }
}
