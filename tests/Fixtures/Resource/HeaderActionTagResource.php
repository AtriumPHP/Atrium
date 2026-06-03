<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\DataProvider\DataQuery;
use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Page\CreatePage;
use Atrium\Page\ListPage;
use Atrium\Page\Page;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Page\HeaderActionEditPage;

/**
 * Fixture exercising page header actions on the edit screen, hosted by the Form
 * component. Scoped to active tags, so the "archive" action (which sets
 * active = false) makes the record fall out of scope — the Form then re-resolves
 * it, finds nothing, and redirects to the list. The actions live on the
 * {@see HeaderActionEditPage}, registered via {@see pages()}.
 */
final class HeaderActionTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'header-action-tag';
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
        return [
            'index' => ListPage::class,
            'create' => CreatePage::class,
            'edit' => HeaderActionEditPage::class,
        ];
    }
}
