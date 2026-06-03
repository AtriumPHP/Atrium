<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Page\ViewPage;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * A resource with a View screen but no `view()` schema: the View page falls back
 * to rendering the `form()` fields read-only.
 */
final class ViewFallbackTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'fallback-view-tag';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([
            TextField::make('name'),
            TextField::make('slug'),
        ]);
    }

    /**
     * @return array<string, class-string<\Atrium\Page\Page>>
     */
    public static function pages(): array
    {
        return [...parent::pages(), 'view' => ViewPage::class];
    }
}
