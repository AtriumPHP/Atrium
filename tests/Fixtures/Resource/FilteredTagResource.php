<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\Filter\SelectFilter;
use Atrium\Table\Filter\TernaryFilter;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Fixture exercising filters: a select filter over the categorical `kind` field
 * and a ternary filter over the boolean `active` field, against the 12 sample
 * Tags (kinds cycle fruit/tool/animal; even ids are active).
 */
final class FilteredTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'filtered-tag';
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table
            ->columns([
                Column::make('name')->searchable(),
                Column::make('kind')->badge(),
                Column::make('active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->options(['fruit' => 'Fruit', 'tool' => 'Tool', 'animal' => 'Animal']),
                TernaryFilter::make('active')->labels('Active', 'Inactive'),
            ]);
    }
}
