<?php

declare(strict_types=1);

namespace Atrium\Table\Action;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;

/**
 * The built-in "view" record action: a link to the resource's read-only View
 * screen at the bare record URL (`/{prefix}/{slug}/{id}`). A table-bound
 * specialisation of the generic {@see Action} and the least-destructive default —
 * gated by the `view` ability, like the screen itself.
 */
final class ViewAction extends Action
{
    public static function make(string $name = 'view'): static
    {
        return parent::make($name)
            ->label('View')
            ->icon('eye')
            ->authorize('view');
    }

    public function getUrl(object $subject, ActionContext $context): string
    {
        return $context->recordRootUrl();
    }

    /**
     * As a header action on a single-record screen the context carries the record
     * id, so resolve the same bare record URL — keeping it a link.
     */
    public function getStandaloneUrl(ActionContext $context): string
    {
        return $context->recordRootUrl();
    }
}
