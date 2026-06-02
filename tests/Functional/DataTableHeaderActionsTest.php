<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Header actions: the table renders its resource's header actions in the card
 * header — by default a "New" link to the create page.
 */
final class DataTableHeaderActionsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testRendersTheCreateHeaderActionAsALink(): void
    {
        $html = $this->createLiveComponent('Atrium:DataTable', [
            'resource' => 'actions-tag',
            'pathPrefix' => '/admin',
        ])->render()->toString();

        // The "New" header action links to the resource's create page.
        self::assertStringContainsString('href="/admin/actions-tag/new"', $html);
        self::assertStringContainsString('New', $html);
    }
}
