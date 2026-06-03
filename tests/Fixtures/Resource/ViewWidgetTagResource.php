<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Schema;
use Atrium\Page\Page;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Page\ViewWidgetTagViewPage;
use Atrium\View\TextEntry;

/**
 * View-widgets fixture: its View page ({@see ViewWidgetTagViewPage}) renders a
 * header band (a stats widget) above the entries and a chart widget below them,
 * exercising the record-scoped View widget bands.
 */
final class ViewWidgetTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'view-widget-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('name')]);
    }

    public function view(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('name')]);
    }

    /**
     * @return array<string, class-string<Page>>
     */
    public static function pages(): array
    {
        return [...parent::pages(), 'view' => ViewWidgetTagViewPage::class];
    }
}
