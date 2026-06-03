<?php

declare(strict_types=1);

namespace Atrium\Page;

/**
 * Default page for the edit action.
 */
class EditPage extends Page
{
    public function getHeading(PageContext $context): string
    {
        return 'Edit '.$context->singularLabel;
    }
}
