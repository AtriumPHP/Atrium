<?php

declare(strict_types=1);

namespace Atrium\Twig\Components;

use Atrium\DataProvider\DataProviderInterface;
use Atrium\Resource\AdminResource;
use Atrium\Resource\ResourceRegistry;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * @internal placement host for a resource's relation managers (REL-08). M1 renders
 * each declared relation as a titled section (the server-driven tab strip and the
 * per-record `->visible($parent)` filter arrive in REL-M2/M3). It only resolves the
 * relation list and embeds each {@see RelationManager} Live Component.
 */
#[AsTwigComponent(name: 'Atrium:RelationManagers', template: '@Atrium/components/relation_managers.html.twig')]
final class RelationManagers
{
    public string $resource = '';
    public string $parentId = '';
    public string $pathPrefix = '';
    public string $screen = 'edit';

    public function __construct(
        private readonly ResourceRegistry $registry,
        private readonly DataProviderInterface $dataProvider,
    ) {
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

    public function hasRelations(): bool
    {
        // Derived from getRelations() so the two stay consistent when M2 adds the
        // per-record ->visible($parent) filter (no empty wrapper when all hidden).
        return [] !== $this->getRelations();
    }

    private function resourceObject(): AdminResource
    {
        return $this->registry->getBySlug($this->resource);
    }
}
