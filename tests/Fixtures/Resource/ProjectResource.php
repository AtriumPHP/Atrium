<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Page\ViewPage;
use Atrium\Relation\Relation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Project;
use Atrium\View\TextEntry;

final class ProjectResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Project::class;
    }

    /** @return array<string, class-string<\Atrium\Page\Page>> */
    public static function pages(): array
    {
        return [...parent::pages(), 'view' => ViewPage::class];
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('name')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([TextField::make('name')]);
    }

    public function view(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('name')]);
    }

    /** @return list<Relation> */
    public function relations(): array
    {
        return [
            Relation::make('tasks')->oneToMany(TaskResource::class)->foreignKey('projectId'),
        ];
    }
}
