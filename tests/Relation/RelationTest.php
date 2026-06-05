<?php

declare(strict_types=1);

namespace Atrium\Tests\Relation;

use Atrium\Form\Schema;
use Atrium\Relation\Relation;
use Atrium\Relation\RelationKind;
use Atrium\Table\TableConfiguration;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Tests\Fixtures\Resource\TagResource;
use PHPUnit\Framework\TestCase;

final class RelationTest extends TestCase
{
    public function testOneToManyCarriesKindTargetAndForeignKey(): void
    {
        $relation = Relation::make('comments')
            ->oneToMany(TagResource::class)
            ->foreignKey('post_id')
            ->recordTitle('body');

        self::assertSame('comments', $relation->getName());
        self::assertSame(RelationKind::OneToMany, $relation->getKind());
        self::assertSame(TagResource::class, $relation->getTargetClass());
        self::assertSame('post_id', $relation->getForeignKey());
        self::assertSame('body', $relation->getRecordTitle());
    }

    public function testManyToManyCarriesPivotConfiguration(): void
    {
        $relation = Relation::make('tags')
            ->manyToMany(TagResource::class)
            ->pivotTable('post_tag')
            ->pivotKeys('post_id', 'tag_id')
            ->pivotColumns(['sort']);

        self::assertSame(RelationKind::ManyToMany, $relation->getKind());
        self::assertSame('post_tag', $relation->getPivotTable());
        self::assertSame('post_id', $relation->getPivotParentKey());
        self::assertSame('tag_id', $relation->getPivotRelatedKey());
        self::assertSame(['sort'], $relation->getPivotColumns());
    }

    public function testLabelDefaultsToHumanisedNameAndReadOnlyOnViewDefaultsTrue(): void
    {
        $relation = Relation::make('blogComments')->oneToMany(TagResource::class)->foreignKey('x');

        self::assertSame('Blog comments', $relation->getLabel());
        self::assertTrue($relation->isReadOnlyOnView());
    }

    public function testTableAndFormClosuresAreApplied(): void
    {
        $relation = Relation::make('comments')
            ->oneToMany(TagResource::class)->foreignKey('x')
            ->table(static fn (TableConfiguration $t): TableConfiguration => $t->paginated(7))
            ->form(static fn (Schema $s): Schema => $s);

        $table = $relation->applyTable(TableConfiguration::make());
        self::assertSame(7, $table->getPerPage());
        self::assertInstanceOf(Schema::class, $relation->applyForm(new Schema()));
    }

    public function testVisibleClosureEvaluatedAgainstParent(): void
    {
        $relation = Relation::make('comments')->oneToMany(TagResource::class)->foreignKey('x')
            ->visible(static fn (object $parent): bool => $parent instanceof Tag && $parent->active);

        self::assertTrue($relation->isVisibleFor(new Tag(active: true)));
        self::assertFalse($relation->isVisibleFor(new Tag(active: false)));
    }
}
