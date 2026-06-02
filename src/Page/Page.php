<?php

declare(strict_types=1);

namespace Atrium\Page;

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
     * Where to send the user after a successful save. Defaults to the resource
     * list; override to change the post-save destination (e.g. stay on the
     * edit screen).
     */
    public function getRedirectUrl(PageContext $context): ?string
    {
        return $context->indexUrl();
    }
}
