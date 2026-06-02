<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\DataTable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class DataTableComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testInitialRenderShowsFirstPage(): void
    {
        $component = $this->createLiveComponent('Atrium:DataTable', ['resource' => 'tag']);
        $html = $component->render()->toString();

        self::assertStringContainsString('Tag 01', $html);
        self::assertStringContainsString('12 results', $html);
        self::assertStringContainsString('Page 1 of 2', $html);
        // Default perPage is 10, so Tag 11/12 are on the next page.
        self::assertStringNotContainsString('Tag 11', $html);
    }

    public function testSearchFiltersRows(): void
    {
        $component = $this->createLiveComponent('Atrium:DataTable', ['resource' => 'tag']);

        $html = $component->set('search', 'tag-07')->render()->toString();

        self::assertStringContainsString('Tag 07', $html);
        self::assertStringContainsString('1 result', $html);
        self::assertStringNotContainsString('Tag 06', $html);
    }

    public function testSearchResetsToFirstPage(): void
    {
        $component = $this->createLiveComponent('Atrium:DataTable', ['resource' => 'tag']);
        $component->call('gotoPage', ['page' => 2]);

        $component->set('search', 'Tag');

        self::assertSame(1, $this->dataTable($component)->page);
    }

    public function testSortTogglesDirection(): void
    {
        $component = $this->createLiveComponent('Atrium:DataTable', ['resource' => 'tag']);

        $component->call('sort', ['field' => 'name']);
        self::assertSame('name', $this->dataTable($component)->sortField);
        self::assertSame('asc', $this->dataTable($component)->sortDirection);

        $component->call('sort', ['field' => 'name']);
        self::assertSame('desc', $this->dataTable($component)->sortDirection);
    }

    public function testSortDescendingPutsLastRecordFirst(): void
    {
        $component = $this->createLiveComponent('Atrium:DataTable', ['resource' => 'tag']);

        $html = $component
            ->call('sort', ['field' => 'name'])
            ->call('sort', ['field' => 'name'])
            ->render()
            ->toString();

        $posTag12 = strpos($html, 'Tag 12');
        $posTag10 = strpos($html, 'Tag 10');

        self::assertNotFalse($posTag12);
        self::assertNotFalse($posTag10);
        self::assertLessThan($posTag10, $posTag12, 'Descending sort should list Tag 12 before Tag 10.');
    }

    public function testNonSortableFieldIsIgnored(): void
    {
        $component = $this->createLiveComponent('Atrium:DataTable', ['resource' => 'tag']);

        // "slug" is not marked sortable on the fixture resource.
        $component->call('sort', ['field' => 'slug']);

        self::assertNull($this->dataTable($component)->sortField);
    }

    private function dataTable(\Symfony\UX\LiveComponent\Test\TestLiveComponent $component): DataTable
    {
        $instance = $component->component();
        self::assertInstanceOf(DataTable::class, $instance);

        return $instance;
    }
}
