<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Content\Text;
use Atrium\Form\Field\CheckboxField;
use Atrium\Form\Field\SelectField;
use Atrium\Form\Field\TextField;
use Atrium\Form\Get;
use Atrium\Form\Schema;
use Atrium\Form\Set;
use Atrium\Layout\Flex;
use Atrium\Layout\Section;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Layout fixture (SCH-*): the same Tag entity as {@see TagResource}, but its form
 * is organised into a Section (two-column grid, full-width span) plus a Flex row
 * — proving the schema tree renders, flattens and saves like a flat form.
 */
final class LayoutTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'layout-tag';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->description('Naming and slug.')
                ->columns(2)
                ->schema([
                    Text::make('All fields marked with an asterisk are required.')
                        ->columnSpanFull(),
                    // Cross-field reactivity (FRM-10): derive slug from name.
                    TextField::make('name')->required()
                        ->afterStateUpdated(static function (mixed $state, Get $get, Set $set): void {
                            $set('slug', self::slugify(\is_scalar($state) ? (string) $state : ''));
                        }),
                    TextField::make('slug')->columnSpanFull(),
                    // Operation-aware visibility (FRM-09): only shown when editing.
                    TextField::make('notes')->visibleOn('edit')->columnSpanFull(),
                ]),
            Flex::make()->from('md')->schema([
                CheckboxField::make('active'),
                SelectField::make('kind')->options(['fruit' => 'Fruit', 'tool' => 'Tool'])->live()->grow(false),
                // Conditional visibility (FRM-08): only when kind is "fruit".
                TextField::make('cultivar')->visible(static fn (Get $get): bool => 'fruit' === $get('kind')),
            ]),
        ]);
    }

    private static function slugify(string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';

        return strtolower(trim($slug, '-'));
    }
}
