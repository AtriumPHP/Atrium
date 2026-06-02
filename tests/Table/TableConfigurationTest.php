<?php

declare(strict_types=1);

namespace Atrium\Tests\Table;

use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use PHPUnit\Framework\TestCase;

final class TableConfigurationTest extends TestCase
{
    public function testCarriesFrameworkDefaults(): void
    {
        $table = TableConfiguration::make();

        self::assertSame([], $table->getColumns());
        self::assertCount(1, $table->getRecordActions());
        self::assertCount(1, $table->getHeaderActions());
        self::assertSame([], $table->getBulkActions());
        self::assertNull($table->getDefaultSortField());
        self::assertSame('asc', $table->getDefaultSortDirection());
        self::assertSame(10, $table->getPerPage());
        self::assertSame([], $table->getPerPageOptions());
    }

    public function testFluentSettersReplaceDefaults(): void
    {
        $table = TableConfiguration::make()
            ->columns([Column::make('name'), Column::make('slug')])
            ->recordActions([])
            ->bulkActions([])
            ->headerActions([]);

        self::assertCount(2, $table->getColumns());
        self::assertSame([], $table->getRecordActions());
        self::assertSame([], $table->getHeaderActions());
    }

    public function testDefaultSortNormalisesDirection(): void
    {
        self::assertSame('desc', TableConfiguration::make()->defaultSort('createdAt', 'DESC')->getDefaultSortDirection());
        self::assertSame('asc', TableConfiguration::make()->defaultSort('name', 'whatever')->getDefaultSortDirection());
        self::assertSame('createdAt', TableConfiguration::make()->defaultSort('createdAt', 'desc')->getDefaultSortField());
    }

    public function testPaginatedSetsSizeAndSortedOptionsIncludingCurrent(): void
    {
        $table = TableConfiguration::make()->paginated(15, [50, 10, 25]);

        self::assertSame(15, $table->getPerPage());
        // Options are sorted and the current size is added if missing.
        self::assertSame([10, 15, 25, 50], $table->getPerPageOptions());
    }

    public function testPaginatedClampsToAtLeastOneAndWithoutOptionsHasNoSelector(): void
    {
        $table = TableConfiguration::make()->paginated(0);

        self::assertSame(1, $table->getPerPage());
        self::assertSame([], $table->getPerPageOptions());
    }

    public function testEmptyStateIsNullByDefaultAndCaptured(): void
    {
        $default = TableConfiguration::make();
        self::assertNull($default->getEmptyHeading());
        self::assertNull($default->getEmptyDescription());
        self::assertNull($default->getEmptyIcon());

        $table = TableConfiguration::make()->emptyState('Nothing here', 'Add one to begin.', 'cube');
        self::assertSame('Nothing here', $table->getEmptyHeading());
        self::assertSame('Add one to begin.', $table->getEmptyDescription());
        self::assertSame('cube', $table->getEmptyIcon());
    }
}
