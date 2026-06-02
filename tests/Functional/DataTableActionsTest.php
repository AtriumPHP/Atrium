<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Twig\Components\DataTable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Record actions: the table renders an Edit link, a Delete server button and a
 * grouped dropdown, and runs the server-driven confirm → delete flow.
 */
final class DataTableActionsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testRendersLinkServerAndGroupedActions(): void
    {
        $html = $this->table()->render()->toString();

        // Edit link points at the edit page (path prefix from the mount prop).
        self::assertMatchesRegularExpression('#href="/admin/actions-tag/[^"]+/edit"#', $html);
        // Delete is a server action (a requestAction trigger), not a link.
        self::assertStringContainsString('data-live-action-param="requestAction"', $html);
        self::assertStringContainsString('data-live-name-param="delete"', $html);
        // The group renders as a <details> dropdown with its child action.
        self::assertStringContainsString('<details', $html);
        self::assertStringContainsString('More', $html);
        self::assertStringContainsString('Duplicate', $html);
    }

    public function testDeleteAsksForConfirmationThenDeletes(): void
    {
        $component = $this->table();

        // Requesting a confirmable action opens the prompt, it does not delete yet.
        $component->call('requestAction', ['name' => 'delete', 'id' => '3']);
        $form = $this->component($component);
        self::assertSame('delete', $form->confirmingAction);
        self::assertSame('3', $form->confirmingId);
        self::assertStringContainsString('Are you sure', $component->render()->toString());
        self::assertSame([], $this->writer()->deleted);

        // Confirming runs the delete through the writer and clears the prompt.
        $component->call('confirmAction');
        self::assertNull($this->component($component)->confirmingAction);
        self::assertCount(1, $this->writer()->deleted);
    }

    public function testHiddenActionCannotBeSurfacedOrRun(): void
    {
        $component = $this->table();

        // 'secret' is visible(false): requesting it must not open a prompt …
        $component->call('requestAction', ['name' => 'secret', 'id' => '3']);
        self::assertNull($this->component($component)->confirmingAction);

        // … and forcing a confirm with the prop set is still a no-op (no run).
        $component->call('confirmAction');
        self::assertNull($this->component($component)->confirmingAction);
    }

    public function testCancellingClearsThePromptWithoutDeleting(): void
    {
        $component = $this->table();

        $component->call('requestAction', ['name' => 'delete', 'id' => '3']);
        $component->call('cancelAction');

        self::assertNull($this->component($component)->confirmingAction);
        self::assertSame([], $this->writer()->deleted);
    }

    private function table(): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:DataTable', [
            'resource' => 'actions-tag',
            'pathPrefix' => '/admin',
        ]);
    }

    private function writer(): ArrayDataWriter
    {
        $writer = self::getContainer()->get(ArrayDataWriter::class);
        self::assertInstanceOf(ArrayDataWriter::class, $writer);

        return $writer;
    }

    private function component(TestLiveComponent $component): DataTable
    {
        $instance = $component->component();
        self::assertInstanceOf(DataTable::class, $instance);

        return $instance;
    }
}
