<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Empty state: when a table has no rows, it renders the resource's configured
 * heading/description (and a default heading when none is configured).
 */
final class DataTableEmptyStateTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testConfiguredEmptyStateShownWhenNoRowsMatch(): void
    {
        $component = $this->createLiveComponent('Atrium:DataTable', [
            'resource' => 'paginated-tag',
            'pathPrefix' => '/admin',
        ]);

        // A search that matches nothing empties the table.
        $component->set('search', 'no-such-tag-zzz');
        $html = $component->render()->toString();

        self::assertStringContainsString('No tags yet', $html);
        self::assertStringContainsString('Create your first tag to get started.', $html);
    }

    public function testDefaultEmptyHeadingWhenNoneConfigured(): void
    {
        $component = $this->createLiveComponent('Atrium:DataTable', [
            'resource' => 'tag',
            'pathPrefix' => '/admin',
        ]);

        $component->set('search', 'no-such-tag-zzz');

        self::assertStringContainsString('No tags found.', $component->render()->toString());
    }
}
