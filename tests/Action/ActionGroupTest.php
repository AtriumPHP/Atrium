<?php

declare(strict_types=1);

namespace Atrium\Tests\Action;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;
use Atrium\Action\ActionContract;
use Atrium\Action\ActionGroup;
use PHPUnit\Framework\TestCase;

final class ActionGroupTest extends TestCase
{
    public function testGroupIsAnActionContract(): void
    {
        $group = ActionGroup::make([Action::make('a')]);

        self::assertInstanceOf(ActionContract::class, $group);
        self::assertInstanceOf(ActionContract::class, Action::make('a'));
    }

    public function testGroupResolvesToANestedView(): void
    {
        $group = ActionGroup::make([
            Action::make('one')->label('One'),
            Action::make('two')->label('Two'),
        ])->label('More')->icon('ellipsis-vertical');

        $view = $group->toView(new \stdClass(), new ActionContext('/admin', 'x', '1'), '1');

        self::assertSame('group', $view['kind']);
        self::assertSame('More', $view['label']);
        self::assertSame('ellipsis-vertical', $view['icon']);
        self::assertIsArray($view['actions']);
        self::assertCount(2, $view['actions']);
        $first = $view['actions'][0];
        self::assertIsArray($first);
        self::assertSame('one', $first['name']);
    }

    public function testGroupOmitsHiddenChildrenFromTheView(): void
    {
        $group = ActionGroup::make([
            Action::make('shown'),
            Action::make('hidden')->hidden(),
        ]);

        $view = $group->toView(new \stdClass(), new ActionContext('', '', ''), null);

        self::assertIsArray($view['actions']);
        self::assertCount(1, $view['actions']);
        $only = $view['actions'][0];
        self::assertIsArray($only);
        self::assertSame('shown', $only['name']);
    }

    public function testGroupVisibilityFollowsItsChildrenButFlattenKeepsAll(): void
    {
        $allHidden = ActionGroup::make([Action::make('x')->hidden()]);
        self::assertFalse($allHidden->isVisibleFor(new \stdClass()));

        $group = ActionGroup::make([Action::make('a'), Action::make('b')]);
        self::assertTrue($group->isVisibleFor(new \stdClass()));
        self::assertCount(2, $group->flatten());
    }
}
