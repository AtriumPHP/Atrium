<?php

declare(strict_types=1);

namespace Atrium\Tests\Action;

use Atrium\Action\Action;
use Atrium\Action\ActionContext;
use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Table\Action\DeleteAction;
use Atrium\Table\Action\EditAction;
use PHPUnit\Framework\TestCase;

final class ActionTest extends TestCase
{
    public function testContextBuildsUrls(): void
    {
        $context = new ActionContext('/admin', 'product', '42');

        self::assertSame('/admin/product', $context->resourceUrl());
        self::assertSame('/admin/product/42/edit', $context->recordUrl('edit'));
    }

    public function testFluentPresentationAndView(): void
    {
        $action = Action::make('publish')
            ->label('Publish')->icon('check')->color('green')->button()->badge(3);

        $view = $action->toView(new \stdClass(), new ActionContext('/admin', 'x', '1'), '1');

        self::assertSame('action', $view['kind']);
        self::assertSame('publish', $view['name']);
        self::assertSame('Publish', $view['label']);
        self::assertSame('check', $view['icon']);
        self::assertSame('green', $view['color']);
        self::assertSame('button', $view['style']);
        self::assertSame(3, $view['badge']);
        self::assertNull($view['url']);
        self::assertFalse($view['confirm']);
        self::assertSame([$action], $action->flatten());
    }

    public function testDefaultStyleIsLinkAndLabelHumanised(): void
    {
        $action = Action::make('sendInvoice');

        self::assertSame('link', $action->getStyle());
        self::assertSame('Send Invoice', $action->getLabel());
    }

    public function testLinkVsServerAction(): void
    {
        $link = Action::make('view')->url(static fn (object $r): string => '/somewhere');
        self::assertFalse($link->isServerAction());
        self::assertSame('/somewhere', $link->getUrl(new \stdClass(), new ActionContext('', '', '')));

        $server = Action::make('ping')->action(static fn (object $r, $w): null => null);
        self::assertTrue($server->isServerAction());
        self::assertNull($server->getUrl(new \stdClass(), new ActionContext('', '', '')));
    }

    public function testVisibilityAndHidden(): void
    {
        self::assertTrue(Action::make('a')->isVisibleFor(new \stdClass()));
        self::assertFalse(Action::make('a')->visible(false)->isVisibleFor(new \stdClass()));
        self::assertFalse(Action::make('a')->visible(static fn (object $r): bool => false)->isVisibleFor(new \stdClass()));
        self::assertFalse(Action::make('a')->hidden()->isVisibleFor(new \stdClass()));
        self::assertTrue(Action::make('a')->hidden(static fn (object $r): bool => false)->isVisibleFor(new \stdClass()));
    }

    public function testConfirmation(): void
    {
        self::assertFalse(Action::make('a')->needsConfirmation());

        $confirmed = Action::make('wipe')->confirmationMessage('Really wipe it?');
        self::assertTrue($confirmed->needsConfirmation());
        self::assertSame('Really wipe it?', $confirmed->getConfirmationMessage());
    }

    public function testEditActionResolvesEditUrl(): void
    {
        $edit = EditAction::make();

        self::assertSame('edit', $edit->getName());
        self::assertSame('Edit', $edit->getLabel());
        self::assertSame('/admin/product/7/edit', $edit->getUrl(new \stdClass(), new ActionContext('/admin', 'product', '7')));
        self::assertFalse($edit->isServerAction());
    }

    public function testDeleteActionIsAConfirmedServerActionThatDeletes(): void
    {
        $delete = DeleteAction::make();

        self::assertSame('delete', $delete->getName());
        self::assertSame('red', $delete->getColor());
        self::assertTrue($delete->needsConfirmation());
        self::assertTrue($delete->isServerAction());

        $writer = new ArrayDataWriter();
        $record = new \stdClass();
        $handler = $delete->getHandler();
        self::assertNotNull($handler);
        $handler($record, $writer);

        self::assertSame([$record], $writer->deleted);
    }
}
