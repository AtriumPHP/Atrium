<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\CheckboxField;
use Atrium\Form\Field\SelectField;
use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Inline (small) resource fixture — mirrors the §9a DX example.
 */
final class TagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function columns(): array
    {
        return [
            Column::make('name')->sortable()->searchable(),
            Column::make('slug')->searchable(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([
            TextField::make('name')->required(),
            TextField::make('slug'),
            CheckboxField::make('active'),
            // Reactive dependent select (FRM-05): "region" options follow "kind".
            SelectField::make('kind')->options(['fruit' => 'Fruit', 'tool' => 'Tool'])->live(),
            SelectField::make('region')->optionsUsing(
                static fn (array $data): array => 'fruit' === ($data['kind'] ?? null)
                    ? ['eu' => 'Europe', 'sa' => 'South America']
                    : ['us' => 'USA', 'cn' => 'China'],
            ),
        ]);
    }
}
