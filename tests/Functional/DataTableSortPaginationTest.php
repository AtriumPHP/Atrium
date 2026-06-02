<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\DataTable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Table-level options: the configured default sort orders the first load, the
 * configured page size and selector drive pagination, and a forged page size is
 * clamped to a configured choice.
 *
 * The fixture (paginated-tag) sorts by name desc with a page size of 5 over the
 * 12 sample Tag records (Tag 01 … Tag 12).
 */
final class DataTableSortPaginationTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testConfiguredDefaultSortOrdersTheFirstLoad(): void
    {
        $instance = $this->instance($this->table());

        self::assertSame('name', $instance->getActiveSortField());
        self::assertSame('desc', $instance->getActiveSortDirection());

        // name desc → "Tag 12" is the first row before the user touches anything.
        $rows = $instance->getRows();
        self::assertNotSame([], $rows);
        self::assertSame('Tag 12', $rows[0]['cells'][0]['value']);
    }

    public function testConfiguredPageSizeAndCount(): void
    {
        $instance = $this->instance($this->table());

        self::assertSame(5, $instance->perPage);
        self::assertCount(5, $instance->getRows());
        self::assertSame(3, $instance->getPageCount(), '12 records / 5 per page → 3 pages.');
    }

    public function testClickingTheActiveSortColumnFlipsDirection(): void
    {
        $component = $this->table();

        // Active sort starts at the configured default (name desc); clicking name flips it.
        $component->call('sort', ['field' => 'name']);

        $instance = $this->instance($component);
        self::assertSame('name', $instance->getActiveSortField());
        self::assertSame('asc', $instance->getActiveSortDirection());
        self::assertSame('Tag 01', $instance->getRows()[0]['cells'][0]['value']);
    }

    public function testChangingPageSizeToAnOfferedValueResetsToPageOne(): void
    {
        $component = $this->table();
        $component->call('gotoPage', ['page' => 3]);

        $component->set('perPage', 10);

        $instance = $this->instance($component);
        self::assertSame(10, $instance->perPage);
        self::assertSame(1, $instance->page, 'Changing the page size returns to page one.');
        self::assertCount(10, $instance->getRows());
    }

    public function testForgedPageSizeIsClampedToAConfiguredChoice(): void
    {
        $component = $this->table();

        // A value outside the offered options must not request a huge page.
        $component->set('perPage', 100000);

        self::assertSame(5, $this->instance($component)->perPage);
    }

    private function table(): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:DataTable', [
            'resource' => 'paginated-tag',
            'pathPrefix' => '/admin',
        ]);
    }

    private function instance(TestLiveComponent $component): DataTable
    {
        $instance = $component->component();
        self::assertInstanceOf(DataTable::class, $instance);

        return $instance;
    }
}
