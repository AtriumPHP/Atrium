<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\DataProvider\DataQuery;
use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Page\CreatePage;
use Atrium\Page\EditPage;
use Atrium\Page\Page;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Page\NoHeaderActionsListPage;

/**
 * Fixture exercising query scoping: the resource only ever exposes *active* tags.
 * The same scope filters the list/count and single-record resolution, so an
 * inactive tag is invisible everywhere — list, edit page and form alike.
 */
final class ScopedTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'scoped-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table
            ->columns([Column::make('name')])
            ->recordActions([]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([TextField::make('name')]);
    }

    public function scopeQuery(DataQuery $query): DataQuery
    {
        return $query->withFilters(['active' => true]);
    }

    /**
     * @return array<string, class-string<Page>>
     */
    public static function pages(): array
    {
        // No "New" button: the scoped list is browse/edit only.
        return [
            'index' => NoHeaderActionsListPage::class,
            'create' => CreatePage::class,
            'edit' => EditPage::class,
        ];
    }
}
