<?php

declare(strict_types=1);

namespace Atrium\Table\Action;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;

/**
 * The built-in "create" header action: a primary button linking to the
 * resource's create page (`/{prefix}/{slug}/new`). A table-bound specialisation
 * of the generic {@see Action}, used as the default
 * {@see \Atrium\Resource\AdminResource::headerActions()}.
 */
final class CreateAction extends Action
{
    public static function make(string $name = 'create'): static
    {
        return parent::make($name)
            ->label('New')
            ->icon('plus')
            ->color('primary')
            ->button();
    }

    public function getStandaloneUrl(ActionContext $context): string
    {
        return $context->resourceUrl().'/new';
    }
}
