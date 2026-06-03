<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Schema;
use Atrium\Layout\Section;
use Atrium\Page\ViewPage;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\View\RepeatableEntry;
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

    public function table(TableConfiguration $table): TableConfiguration
    {
        // A column so the list renders cells (the clickable-row overlay attaches to
        // the first one); the row target stays the default (view).
        return $table->columns([Column::make('name')]);
    }

    public function view(Schema $schema): Schema
    {
        // Wrapped in a Section so the functional test also exercises that the
        // record propagates through a nested layout container to the entries.
        return $schema->components([
            Section::make('Details')->schema([
                TextEntry::make('name')->weight('semibold'),
                TextEntry::make('slug'),
                TextEntry::make('active')->badge()
                    ->formatStateUsing(static fn (mixed $state): string => $state ? 'Active' : 'Inactive')
                    ->color(static fn (mixed $state): string => $state ? 'success' : 'gray'),
                TextEntry::make('kind')->badge(),
            ]),
            // A repeatable over a synthesised list: exercises that each item is
            // bound as the record for the nested entries (the recursive render).
            RepeatableEntry::make('variants')->columns(2)->schema([
                TextEntry::make('label')->weight('semibold'),
                TextEntry::make('qty')->label('Qty'),
            ])->getStateUsing(static fn (Tag $tag): array => [
                (object) ['label' => 'Small', 'qty' => 1],
                (object) ['label' => 'Large', 'qty' => 2],
            ]),
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
