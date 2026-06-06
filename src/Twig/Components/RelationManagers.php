<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\DataProvider\DataProviderInterface;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * @internal placement host for a resource's relation managers (REL-08). A single
 * relation renders as a titled section; several render as a server-driven tab
 * strip that mounts only the active {@see RelationManager} (held in
 * {@see $activeRelation}), so server load is one manager at a time. Relations
 * hidden by a per-parent `->visible($parent)` predicate are dropped.
 */
#[AsLiveComponent(name: 'Atrium:RelationManagers', template: '@Atrium/components/relation_managers.html.twig')]
final class RelationManagers
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $resource = '';

    #[LiveProp]
    public string $parentId = '';

    #[LiveProp]
    public string $pathPrefix = '';

    #[LiveProp]
    public string $screen = 'edit';

    /** The relation whose manager is shown (the active tab). */
    #[LiveProp(writable: true)]
    public string $activeRelation = '';

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
    ) {
    }

    #[LiveAction]
    public function selectTab(#[LiveArg] string $relation): void
    {
        if (\in_array($relation, $this->relationNames(), true)) {
            $this->activeRelation = $relation;
        }
    }

    /**
     * @return list<array{name: string, label: string, icon: ?string}>
     */
    public function getRelations(): array
    {
        $parent = $this->loadParent();

        $views = [];
        foreach ($this->resourceObject()->relations() as $relation) {
            // Drop relations a per-parent `visible($parent)` predicate hides. When
            // the parent can't be loaded (e.g. out of scope) nothing is shown.
            if (null === $parent || !$relation->isVisibleFor($parent)) {
                continue;
            }

            $views[] = [
                'name' => $relation->getName(),
                'label' => $relation->getLabel(),
                'icon' => $relation->getIcon(),
            ];
        }

        return $views;
    }

    /**
     * The relation shown now: the selected one if still visible, else the first.
     */
    public function getActiveRelation(): ?string
    {
        $names = $this->relationNames();
        if ('' !== $this->activeRelation && \in_array($this->activeRelation, $names, true)) {
            return $this->activeRelation;
        }

        return $names[0] ?? null;
    }

    public function hasRelations(): bool
    {
        return [] !== $this->getRelations();
    }

    /**
     * @return list<string>
     */
    private function relationNames(): array
    {
        return array_map(static fn (array $relation): string => $relation['name'], $this->getRelations());
    }

    private function loadParent(): ?object
    {
        $resource = $this->resourceObject();

        return $this->dataProvider->find(
            $resource->getEntityClass(),
            $this->parentId,
            $resource->scopeFilters(),
            $resource->getIdentifierField(),
        );
    }

    private function resourceObject(): AdminResource
    {
        return $this->registry->getBySlug($this->resource);
    }
}
