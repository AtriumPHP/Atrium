<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Relation\ParentRelation;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Task;
use Atrium\View\TextEntry;

final class TaskResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Task::class;
    }

    public function parent(): ParentRelation
    {
        return ParentRelation::make(ProjectResource::class)
            ->relationship('tasks')
            ->foreignKey('projectId')
            // Titles the PARENT (Project) record in the breadcrumb — Project's
            // display attribute is `name`, not `title`.
            ->recordTitle('name');
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('title')])->recordUrl('view');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([TextField::make('title')]);
    }

    public function view(Schema $schema): Schema
    {
        return $schema->components([TextEntry::make('title')]);
    }
}
