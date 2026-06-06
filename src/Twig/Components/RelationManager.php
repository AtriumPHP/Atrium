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
use Atrium\Table\Action\BulkDeleteAction;
use Atrium\Table\Action\DeleteAction;
use Atrium\Table\TableConfiguration;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
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

    /** Open modal: 'create' | 'edit' | 'associate' | null (closed). */
    #[LiveProp]
    public ?string $modalMode = null;

    /** The id being edited in the modal (null for create). */
    #[LiveProp]
    public ?string $modalRecordId = null;

    /** The picked record id in the Associate modal. */
    #[LiveProp(writable: true)]
    public string $associateId = '';

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
        // Parent-scoped: a child of another parent (a forged id) must resolve to
        // null, so a mutating row action can never reach across parents.
        $filters = $this->target()->scopeFilters();
        $parentId = $this->accessor->getValue($this->parent(), $this->descriptor()->parentIdField);
        $filters[(string) $this->descriptor()->foreignKey] = \is_scalar($parentId) ? $parentId : null;

        return $this->dataProvider->find(
            $this->entityClass(),
            $id,
            $filters,
            $this->descriptor()->childIdField,
        );
    }

    /** @return list<Action> */
    public function getHeaderActions(): array
    {
        return [];   // the New / Attach affordances are template triggers (M2)
    }

    public function getRecordActions(): array
    {
        if ($this->isReadOnly()) {
            return [];
        }

        return [
            $this->dissociateAction(),
            DeleteAction::make(),
        ];
    }

    /**
     * Unlink (clear the FK) the row's record from this parent through the relation
     * provider; the record itself persists. Gated by the parent resource's
     * canDissociate (see {@see actionAuthorized()}).
     */
    private function dissociateAction(): Action
    {
        $descriptor = $this->descriptor();
        $parent = $this->parent();

        return Action::make('dissociate')
            ->label('Detach')
            ->icon('unlink')
            ->color('gray')
            ->authorize('dissociate')
            ->confirmationMessage('Detach this record? It will be unlinked but not deleted.')
            ->action(function (object $child) use ($descriptor, $parent): void {
                $this->relationProvider->dissociate($descriptor, $parent, $child);
            });
    }

    /**
     * Owned abilities (create/edit/delete/view) gate on the target resource via the
     * base; relation link/unlink gate on the *parent* resource's canAssociate/
     * canDissociate, which take both the parent record and the child (REL-12).
     */
    protected function actionAuthorized(Action $action, ?object $record): bool
    {
        return match ($action->getAbility()) {
            'associate' => null !== $record && $this->parentResource()->canAssociate($this->parent(), $record),
            'dissociate' => null !== $record && $this->parentResource()->canDissociate($this->parent(), $record),
            default => parent::actionAuthorized($action, $record),
        };
    }

    public function getBulkActions(): array
    {
        if ($this->isReadOnly()) {
            return [];
        }

        return [
            BulkDeleteAction::make(),
        ];
    }

    /**
     * A relation manager is read-only on the View screen when the relation opts in
     * (readOnlyOnView, default true): every mutator is hidden, leaving the list,
     * pagination and search.
     */
    private function isReadOnly(): bool
    {
        return 'view' === $this->screen && $this->relation()->isReadOnlyOnView();
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

    // -- Modal create / edit (REL-05) -------------------------------------

    #[LiveAction]
    public function openCreate(): void
    {
        if ($this->isReadOnly() || !$this->target()->canCreate()) {
            return;
        }

        $this->modalMode = 'create';
        $this->modalRecordId = null;
    }

    #[LiveAction]
    public function openEdit(#[LiveArg] string $id): void
    {
        $record = $this->findRecord($id);   // parent-scoped
        if ($this->isReadOnly() || null === $record || !$this->target()->canEdit($record)) {
            return;
        }

        $this->modalMode = 'edit';
        $this->modalRecordId = $id;
    }

    #[LiveAction]
    public function closeModal(): void
    {
        $this->modalMode = null;
        $this->modalRecordId = null;
        $this->associateId = '';
    }

    // -- Associate an existing record (REL-07) ----------------------------

    #[LiveAction]
    public function openAssociate(): void
    {
        if ($this->isReadOnly()) {
            return;
        }

        $this->modalMode = 'associate';
        $this->associateId = '';
    }

    #[LiveAction]
    public function submitAssociate(): void
    {
        if ($this->isReadOnly() || '' === $this->associateId) {
            return;
        }

        // Re-resolve from listLinkable so a forged id (already-linked or out of the
        // target's scope) is refused; then re-check authorization at execution.
        $child = $this->findLinkable($this->associateId);
        if (null === $child || !$this->parentResource()->canAssociate($this->parent(), $child)) {
            return;
        }

        $this->writer->transactional(function () use ($child): void {
            $this->relationProvider->associate($this->descriptor(), $this->parent(), $child);
        });

        $this->closeModal();
        $this->refreshRecords();
    }

    public function canAssociateRelated(): bool
    {
        return !$this->isReadOnly();
    }

    /**
     * Options for the Associate picker: linkable records (not already linked,
     * within the target's scope) titled by the relation's recordTitle, keyed by id.
     *
     * @return array<string, string>
     */
    public function getLinkableOptions(): array
    {
        $options = [];
        foreach ($this->relationProvider->listLinkable($this->descriptor(), $this->parent(), new DataQuery(offset: 0, limit: 100)) as $record) {
            $id = $this->recordId($record);
            if (null === $id) {
                continue;
            }
            $title = $this->accessor->getValue($record, $this->descriptor()->recordTitleAttribute);
            $options[$id] = \is_scalar($title) ? (string) $title : $id;
        }

        return $options;
    }

    private function findLinkable(string $id): ?object
    {
        // O(N) verify-then-associate: a direct findLinkable(id) lands in a later
        // milestone. The cap bounds the work for very large candidate sets.
        foreach ($this->relationProvider->listLinkable($this->descriptor(), $this->parent(), new DataQuery(offset: 0, limit: 1000)) as $record) {
            if ($this->recordId($record) === $id) {
                return $record;
            }
        }

        return null;
    }

    #[LiveListener('relation:saved')]
    public function onRelationSaved(): void
    {
        $this->closeModal();
        $this->refreshRecords();
    }

    #[LiveListener('relation:cancel')]
    public function onRelationCancel(): void
    {
        $this->closeModal();
    }

    public function isModalOpen(): bool
    {
        return null !== $this->modalMode;
    }

    /**
     * Props for the nested {@see Form} hosted in the create/edit modal. On create
     * the parent foreign key is preset so the child is linked in one transaction.
     *
     * @return array<string, mixed>
     */
    public function getModalFormProps(): array
    {
        $preset = 'create' === $this->modalMode
            ? [(string) $this->descriptor()->foreignKey => $this->parentIdValue()]
            : [];

        return [
            'resource' => $this->target()->getSlug(),
            'entityId' => $this->modalRecordId,
            'embedded' => true,
            'presetValues' => $preset,
            'notifyEvent' => 'relation:saved',
            'pathPrefix' => $this->pathPrefix,
        ];
    }

    public function canCreateRelated(): bool
    {
        return !$this->isReadOnly() && $this->target()->canCreate();
    }

    public function getSingularLabel(): string
    {
        return $this->target()->getSingularLabel();
    }

    /**
     * Row clicks open the edit modal (unless read-only). This is a table-level
     * flag, so every row is rendered clickable; `openEdit()` then re-checks
     * `canEdit($record)` per record and no-ops for ones the user may not edit.
     */
    public function getRowAction(): ?string
    {
        return $this->isReadOnly() ? null : 'openEdit';
    }

    private function parentIdValue(): string
    {
        $value = $this->accessor->getValue($this->parent(), $this->descriptor()->parentIdField);
        if (!\is_scalar($value)) {
            // A non-scalar parent identifier (e.g. a value-object id) cannot be
            // carried as a preset FK through the form's serialized state. Fail loud
            // rather than silently linking the child to an empty key.
            throw new \RuntimeException(\sprintf('Relation "%s" cannot preset a non-scalar parent identifier from "%s".', $this->relationName, $this->descriptor()->parentIdField));
        }

        return (string) $value;
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
