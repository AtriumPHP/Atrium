<?php

declare(strict_types=1);

namespace Atrium\Table\Action;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;

/**
 * The built-in "edit" record action: a link to the resource's edit page
 * (`/{prefix}/{slug}/{id}/edit`). A table-bound specialisation of the generic
 * {@see Action}; override label/icon/visibility like any action.
 */
final class EditAction extends Action
{
    public static function make(string $name = 'edit'): static
    {
        return parent::make($name)
            ->label('Edit')
            ->icon('pencil');
    }

    public function getUrl(object $subject, ActionContext $context): string
    {
        return $context->recordUrl('edit');
    }
}
