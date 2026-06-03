<?php

declare(strict_types=1);

namespace Atrium\Page;

/**
 * Default page for the create action.
 */
class CreatePage extends Page
{
    public function getHeading(PageContext $context): string
    {
        return 'New '.$context->singularLabel;
    }
}
