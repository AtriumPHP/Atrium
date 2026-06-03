<?php

declare(strict_types=1);

namespace Atrium\Page;

use Atrium\Action\Action;
use Atrium\Table\Action\DeleteAction;
use Atrium\Table\Action\EditAction;

/**
 * Default page for the read-only View action (VIEW-11).
 *
 * Owns the View screen's presentation: the heading and the header actions
 * (Edit + Delete by default). The record's content comes from the resource's
 * {@see \Atrium\Resource\AdminResource::view()} schema. A resource opts into a
 * View screen by registering this page (or a subclass) under the `'view'` key of
 * its {@see \Atrium\Resource\AdminResource::pages()}.
 */
class ViewPage extends Page
{
    public function getHeading(PageContext $context): string
    {
        return 'View '.$context->singularLabel;
    }

    /**
     * @return list<Action>
     */
    public function getHeaderActions(PageContext $context): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }

    /**
     * Widgets shown **above** the record's entries (VIEW-16) — a band of
     * stats/charts scoped to this record (the slots receive its id in context).
     * Empty by default; compose them with the layout primitives (`Grid`,
     * `Section`, …) holding {@see \Atrium\Widget\WidgetSlot}s, exactly like a
     * dashboard or the list bands.
     */
    public function headerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        return $config;
    }

    /**
     * Widgets shown **below** the record's entries (VIEW-16). Same record-scoped
     * semantics as {@see headerWidgets()}.
     */
    public function footerWidgets(ListWidgetsConfiguration $config): ListWidgetsConfiguration
    {
        return $config;
    }
}
