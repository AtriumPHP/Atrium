<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Twig\Components\DataTable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Row selection and bulk actions: selecting rows (one, a page, or all matching
 * the query) drives a server-held selection, and a confirmable bulk action runs
 * against the whole selection — across pages — through the writer.
 *
 * The fixture seeds 12 Tag records with a default page size of 10, so page one
 * holds 10 of 12 — enough to exercise the select-all-across-pages path.
 */
final class DataTableBulkActionsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testTogglingARowSelectsItAndShowsTheBulkBar(): void
    {
        $component = $this->table();

        $component->call('toggleRecord', ['id' => '3']);

        $instance = $this->instance($component);
        self::assertSame(['3'], $instance->selected);
        self::assertTrue($instance->hasSelection());
        self::assertSame(1, $instance->getSelectedCount());

        $html = $component->render()->toString();
        self::assertStringContainsString('1 selected', $html);
        self::assertStringContainsString('data-live-action-param="requestBulkAction"', $html);
    }

    public function testTogglingThePageSelectsEveryVisibleRow(): void
    {
        $component = $this->table();

        $component->call('togglePage');

        $instance = $this->instance($component);
        self::assertSame(10, $instance->getSelectedCount(), 'Page one holds 10 of the 12 records.');
        self::assertTrue($instance->isPageFullySelected());
        // More records exist beyond the page, so "select all matching" is offered.
        self::assertTrue($instance->canSelectAllMatching());
        self::assertStringContainsString('Select all 12', $component->render()->toString());
    }

    public function testSelectAllMatchingCoversEveryRecordAcrossPages(): void
    {
        $component = $this->table();

        $component->call('togglePage');
        $component->call('selectAllMatching');

        $instance = $this->instance($component);
        self::assertTrue($instance->isSelectAllActive());
        self::assertSame(12, $instance->getSelectedCount());
        self::assertFalse($instance->canSelectAllMatching(), 'Nothing more to select.');
    }

    public function testExcludingARowWhileSelectAllReducesTheCount(): void
    {
        $component = $this->table();

        $component->call('selectAllMatching');
        $component->call('toggleRecord', ['id' => '5']);

        $instance = $this->instance($component);
        self::assertSame(['5'], $instance->excluded);
        self::assertSame(11, $instance->getSelectedCount());
        self::assertFalse($instance->isRecordSelected('5'));
        self::assertTrue($instance->isRecordSelected('6'));
    }

    public function testBulkDeleteConfirmsThenDeletesTheSelection(): void
    {
        $component = $this->table();

        $component->call('toggleRecord', ['id' => '1']);
        $component->call('toggleRecord', ['id' => '2']);
        $component->call('toggleRecord', ['id' => '3']);

        // Requesting the confirmable bulk action opens the prompt, deletes nothing.
        $component->call('requestBulkAction', ['name' => 'delete']);
        self::assertSame('delete', $this->instance($component)->confirmingBulkAction);
        // The prompt shows the action label and a count-aware body.
        self::assertStringContainsString('Delete selected', $component->render()->toString());
        self::assertStringContainsString('3 selected records', $component->render()->toString());
        self::assertSame([], $this->writer()->deleted);

        // Confirming runs the delete over the whole selection and clears it.
        $component->call('confirmBulkAction');
        $instance = $this->instance($component);
        self::assertNull($instance->confirmingBulkAction);
        self::assertCount(3, $this->writer()->deleted);
        self::assertFalse($instance->hasSelection());
    }

    public function testSelectAllBulkDeleteRemovesEveryMatchingRecord(): void
    {
        $component = $this->table();

        $component->call('selectAllMatching');
        $component->call('requestBulkAction', ['name' => 'delete']);
        $component->call('confirmBulkAction');

        // Every matching record across all pages is deleted, not just the page.
        self::assertCount(12, $this->writer()->deleted);
        self::assertFalse($this->instance($component)->hasSelection());
    }

    public function testHiddenBulkActionCannotBeSurfacedOrRun(): void
    {
        $component = $this->table();

        $component->call('toggleRecord', ['id' => '1']);

        // 'purge' is visible(false): requesting it must not open a prompt …
        $component->call('requestBulkAction', ['name' => 'purge']);
        self::assertNull($this->instance($component)->confirmingBulkAction);

        // … and forcing a confirm with the prop set is still a no-op.
        $component->call('confirmBulkAction');
        self::assertNull($this->instance($component)->confirmingBulkAction);
    }

    public function testClearingSelectionEmptiesIt(): void
    {
        $component = $this->table();

        $component->call('toggleRecord', ['id' => '1']);
        $component->call('clearSelection');

        $instance = $this->instance($component);
        self::assertFalse($instance->hasSelection());
        self::assertSame([], $instance->selected);
        self::assertFalse($instance->isSelectAllActive());
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

    private function instance(TestLiveComponent $component): DataTable
    {
        $instance = $component->component();
        self::assertInstanceOf(DataTable::class, $instance);

        return $instance;
    }
}
