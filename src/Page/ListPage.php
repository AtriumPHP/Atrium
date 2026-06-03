<?php

declare(strict_types=1);

namespace Atrium\Page;

use Atrium\Table\Action\CreateAction;

/**
 * Default page for the resource list (index) action.
 *
 * Owns the list screen's presentation: the default "New" header action, and the
 * optional widget bands above ({@see headerWidgets()}) and below
 * ({@see footerWidgets()}) the table. The table itself (columns, filters, record
 * actions) is the resource's {@see \Atrium\Resource\AdminResource::table()} — data
 * config stays on the resource, per-screen presentation lives here.
 */
class ListPage extends Page
{
    /**
     * The list's header actions — by default the "New" button. Override to add
     * (Import, …) or to drop it (`return []` for a read-only list).
     *
     * @return list<\Atrium\Action\Action>
     */
    public function getHeaderActions(PageContext $context): array
    {
        return [CreateAction::make()];
    }

    /**
     * Widgets shown **above** the list table (LW-01) — a band of stats/charts.
     * Empty by default; compose them with the layout primitives (`Grid`,
     * `Section`, …) holding {@see \Atrium\Widget\WidgetSlot}s, exactly like a
     * dashboard.
     *
     * The widgets are independent of the table's live state — they do not react to
     * the current search/filters (that state lives in the sibling DataTable
     * component); they reflect the unfiltered resource.
     */
    public function headerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        return $config;
    }

    /**
     * Widgets shown **below** the list table (LW-01). Same independence semantics
     * as {@see headerWidgets()}.
     */
    public function footerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        return $config;
    }
}
