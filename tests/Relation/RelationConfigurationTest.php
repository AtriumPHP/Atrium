<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Relation\Relation;
use Atrium\Relation\RelationManagerConfiguration;
use Atrium\Table\Column;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Resource\CommentRelResource;
use PHPUnit\Framework\TestCase;

final class RelationConfigurationTest extends TestCase
{
    public function testResolvesNullWhenNoUsingClass(): void
    {
        $relation = Relation::make('comments')->oneToMany(CommentRelResource::class)->foreignKey('postId');
        self::assertNull($relation->resolveConfiguration());
    }

    public function testResolvesTheUsingConfigurationInstance(): void
    {
        $relation = Relation::make('comments')->oneToMany(CommentRelResource::class)
            ->foreignKey('postId')->using(SampleCommentConfig::class);

        $config = $relation->resolveConfiguration();
        self::assertInstanceOf(SampleCommentConfig::class, $config);

        // The config can shape the table + form.
        $table = $config->table(TableConfiguration::make());
        self::assertCount(1, $table->getColumns());
        $schema = $config->form(new Schema());
        self::assertNotSame([], $schema->getComponents());
    }
}

final class SampleCommentConfig extends RelationManagerConfiguration
{
    public function table(TableConfiguration $table): TableConfiguration
    {
        return $table->columns([Column::make('body')]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([TextField::make('body')]);
    }
}
