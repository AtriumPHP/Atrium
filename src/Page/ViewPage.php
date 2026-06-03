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
}
