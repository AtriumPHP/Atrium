<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Resource\AdminResource;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Comment;

final class CommentRelResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Comment::class;
    }

    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('body')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextField::make('body'),
        ]);
    }
}
