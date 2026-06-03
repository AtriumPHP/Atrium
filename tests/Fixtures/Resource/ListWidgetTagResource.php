<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Page\CreatePage;
use Atrium\Page\EditPage;
use Atrium\Page\Page;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Page\ListWidgetTagListPage;

/**
 * List-widgets fixture: its list page ({@see ListWidgetTagListPage}) renders a
 * header band (a stats widget nested in a Grid) above the table and a chart widget
 * below it — exercising the page-owned header/footer widget slots.
 */
final class ListWidgetTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'list-widget-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('name')->searchable()]);
    }

    /**
     * @return array<string, class-string<Page>>
     */
    public static function pages(): array
    {
        return [
            'index' => ListWidgetTagListPage::class,
            'create' => CreatePage::class,
            'edit' => EditPage::class,
        ];
    }
}
