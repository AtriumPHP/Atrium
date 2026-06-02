<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\DataTable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * Filters narrow the table through the data provider. The fixture (filtered-tag)
 * exposes a select filter on `kind` and a ternary filter on `active` over the 12
 * sample Tags: kinds cycle fruit/tool/animal (4 each) and even ids are active
 * (6 active). So fruit∩active = ids {4, 10} = 2.
 */
final class DataTableFiltersTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testNoFilterShowsEveryRecord(): void
    {
        $instance = $this->instance($this->table());

        self::assertSame(12, $instance->getTotalCount());
        self::assertFalse($instance->hasActiveFilters());
    }

    public function testSelectFilterNarrowsToOneCategory(): void
    {
        $component = $this->table();
        $component->set('filterValues', ['kind' => 'fruit']);

        $instance = $this->instance($component);
        self::assertSame(4, $instance->getTotalCount());
        self::assertTrue($instance->hasActiveFilters());
    }

    public function testTernaryFilterNarrowsToBoolean(): void
    {
        $component = $this->table();
        $component->set('filterValues', ['active' => '1']);

        self::assertSame(6, $this->instance($component)->getTotalCount());
    }

    public function testFiltersCombine(): void
    {
        $component = $this->table();
        $component->set('filterValues', ['kind' => 'fruit', 'active' => '1']);

        self::assertSame(2, $this->instance($component)->getTotalCount());
    }

    public function testResetFiltersClearsThem(): void
    {
        $component = $this->table();
        $component->set('filterValues', ['kind' => 'fruit']);
        $component->call('resetFilters');

        $instance = $this->instance($component);
        self::assertSame(12, $instance->getTotalCount());
        self::assertSame([], $instance->filterValues);
    }

    public function testForgedFilterKeyIsIgnored(): void
    {
        $component = $this->table();
        // A value for a filter name the resource never configured must not narrow.
        $component->set('filterValues', ['not_a_filter' => 'whatever']);

        $instance = $this->instance($component);
        self::assertSame(12, $instance->getTotalCount());
        self::assertFalse($instance->hasActiveFilters());
    }

    private function table(): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:DataTable', [
            'resource' => 'filtered-tag',
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
