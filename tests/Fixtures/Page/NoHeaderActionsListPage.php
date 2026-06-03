<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Page;

use Atrium\Page\ListPage;
use Atrium\Page\PageContext;

/**
 * A list page that drops the default "New" header action — for a read-only or
 * scoped list that should not offer record creation.
 */
final class NoHeaderActionsListPage extends ListPage
{
    public function getHeaderActions(PageContext $context): array
    {
        return [];
    }
}
