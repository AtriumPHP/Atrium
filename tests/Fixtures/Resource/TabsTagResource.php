<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\CheckboxField;
use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Layout\Tab;
use Atrium\Layout\Tabs;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Tabs fixture (SCH-10): a two-tab form. `name` (required) lives in the second
 * tab, so an empty save proves the form focuses the tab holding the error.
 */
final class TabsTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'tabs-tag';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()->tabs([
                Tab::make('Main')->schema([
                    TextField::make('slug'),
                ]),
                Tab::make('Details')->icon('cube')->badge('2')->columns(2)->schema([
                    TextField::make('name')->required(),
                    CheckboxField::make('active'),
                ]),
            ]),
        ]);
    }
}
