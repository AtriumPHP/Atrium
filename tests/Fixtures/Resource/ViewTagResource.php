<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Schema;
use Atrium\Page\ViewPage;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\View\TextEntry;

/**
 * A resource that opts into a read-only View screen with a custom `view()` of
 * entries. `$allowView` lets a test deny the `view` ability.
 */
final class ViewTagResource extends AdminResource
{
    public static bool $allowView = true;

    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'view-tag';
    }

    public function view(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->weight('semibold'),
            TextEntry::make('slug'),
            TextEntry::make('active')->badge()
                ->formatStateUsing(static fn (mixed $state): string => $state ? 'Active' : 'Inactive')
                ->color(static fn (mixed $state): string => $state ? 'success' : 'gray'),
            TextEntry::make('kind')->badge(),
        ]);
    }

    public function canView(object $record): bool
    {
        return self::$allowView;
    }

    /**
     * @return array<string, class-string<\Atrium\Page\Page>>
     */
    public static function pages(): array
    {
        return [...parent::pages(), 'view' => ViewPage::class];
    }

    public static function reset(): void
    {
        self::$allowView = true;
    }
}
