<?php

declare(strict_types=1);

namespace Atrium\Page;

use Atrium\Action\Action;

/**
 * A Page is a controller/descriptor class, NOT a Live Component (LAY-03).
 *
 * It owns lifecycle hooks for a panel screen; the reactive widgets embedded in
 * the page (table, form) are the Live Components. Resources expose their pages
 * via {@see \Atrium\Resource\AdminResource::pages()} and customise behaviour by
 * subclassing the base pages and overriding hooks.
 */
abstract class Page
{
    /**
     * The screen's browser `<title>`. Defaults to the heading.
     */
    public function getTitle(PageContext $context): string
    {
        return $this->getHeading($context);
    }

    /**
     * The screen's `<h1>`. The base default is the resource's plural label
     * (suited to the list screen); the create/edit pages override it.
     */
    public function getHeading(PageContext $context): string
    {
        return $context->pluralLabel;
    }

    /**
     * An optional sub-line under the heading; null for none.
     */
    public function getSubheading(PageContext $context): ?string
    {
        return null;
    }

    /**
     * Header actions for this screen, or null to defer to the resource's inline
     * {@see \Atrium\Resource\AdminResource::getHeaderActions()}. Override to take
     * control of the screen's header buttons; they are rendered and dispatched by
     * the screen's Live Component (server-driven actions on the edit screen run
     * against the loaded record).
     *
     * @return list<Action>|null
     */
    public function getHeaderActions(PageContext $context): ?array
    {
        return null;
    }

    /**
     * Where to send the user after a successful save. Defaults to the resource
     * list; override to change the post-save destination (e.g. stay on the
     * edit screen).
     */
    public function getRedirectUrl(PageContext $context): ?string
    {
        return $context->indexUrl();
    }
}
