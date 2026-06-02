<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Content\Text;
use Atrium\Form\Field\CheckboxField;
use Atrium\Form\Field\SelectField;
use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
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
                    TextField::make('name')->required(),
                    TextField::make('slug')->columnSpanFull(),
                ]),
            Flex::make()->from('md')->schema([
                CheckboxField::make('active'),
                SelectField::make('kind')->options(['fruit' => 'Fruit', 'tool' => 'Tool'])->live()->grow(false),
            ]),
        ]);
    }
}
