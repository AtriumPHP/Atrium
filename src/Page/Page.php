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
     * Header actions for this screen — the buttons above it (the list's "New", an
     * edit-screen Delete). The base default is none; {@see ListPage} adds the
     * "New" button, and a custom page overrides this to add its own. They are
     * rendered and dispatched by the screen's Live Component (server-driven actions
     * on the edit screen run against the loaded record).
     *
     * @return list<Action>
     */
    public function getHeaderActions(PageContext $context): array
    {
        return [];
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
