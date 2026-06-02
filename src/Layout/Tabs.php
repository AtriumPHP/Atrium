<?php

declare(strict_types=1);

namespace Atrium\Layout;

/**
 * A tabbed container (SCH-10): a row of tab buttons over a set of {@see Tab}
 * panels, only one of which is shown at a time.
 *
 * The active tab is **server state**, held by the host Live Component and
 * toggled through a live action — not client JavaScript. Every panel is still
 * rendered on each request (inactive ones hidden), so a server re-render only
 * flips an attribute: inputs in other tabs stay in the DOM and keep their
 * in-progress values, and the active tab survives unrelated re-renders.
 *
 * The container has a stable {@see getId()} (derived from its tabs, or set
 * explicitly) used to key that active-tab state.
 */
final class Tabs extends LayoutComponent
{
    private ?string $id = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * The tab panels. Also fixes this container's id (from the tab set) so it
     * stays stable even if visibility filtering later drops a tab.
     *
     * @param list<Tab> $tabs
     */
    public function tabs(array $tabs): self
    {
        $this->schema($tabs);
        $this->id ??= 'tabs-'.substr(md5(implode('|', array_map(static fn (Tab $tab): string => $tab->getId(), $tabs))), 0, 8);

        return $this;
    }

    /**
     * Override the auto-derived id (only needed for two otherwise-identical
     * tab sets on one schema).
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

        return 'tabs-'.substr(md5(implode('|', array_map(static fn (Tab $tab): string => $tab->getId(), $this->getTabs()))), 0, 8);
    }

    /**
     * @return list<Tab>
     */
    public function getTabs(): array
    {
        return array_values(array_filter(
            $this->getChildComponents(),
            static fn (Component $component): bool => $component instanceof Tab,
        ));
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/layout/tabs.html.twig';
    }
}
