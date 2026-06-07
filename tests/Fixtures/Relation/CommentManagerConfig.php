<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Relation;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Relation\RelationManagerConfiguration;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;

final class CommentManagerConfig extends RelationManagerConfiguration
{
    public function table(TableConfiguration $table): TableConfiguration
    {
        // A distinctive column label so the test can prove the config was used.
        return $table->columns([Column::make('body')->label('Extracted Body')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([TextField::make('body')->label('Extracted Comment')]);
    }
}
