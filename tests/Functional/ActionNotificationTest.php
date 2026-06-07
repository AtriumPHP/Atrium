<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataQuery;
use Atrium\Notification\Notifier;
use Atrium\Resource\ResourceRegistry;
use Atrium\Tests\Fixtures\Resource\ViewTagResource;
use Atrium\Twig\Components\RecordActions;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * The built-in delete action raises a real success toast, routed to the right
 * channel for the host's outcome: the table stays in place and emits on the live
 * channel; the View screen's delete redirects to the list, so the toast is queued
 * on the flash bag instead (a live emit would be lost on the navigation).
 */
final class ActionNotificationTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
        ViewTagResource::reset();
    }

    public function testStayingDeleteEmitsLiveNotification(): void
    {
        $component = $this->createLiveComponent('Atrium:DataTable', [
            'resource' => 'actions-tag',
            'pathPrefix' => '/admin',
        ]);

        // The table mutates the list in place (no redirect): the toast fires on the
        // live channel once the delete commits.
        $component->call('requestAction', ['name' => 'delete', 'id' => '3']);
        $component->call('confirmAction');

        self::assertComponentEmitEvent($component, 'atrium:notification');
    }

    public function testRedirectingDeleteQueuesNotificationOnTheFlashBag(): void
    {
        // The RecordActions delete (View screen) redirects to the list once the
        // record is gone, so the success toast must go on the flash channel to
        // survive the navigation. Drive the host directly with a Notifier bound to a
        // session we control; a data provider that reports the record gone after the
        // delete forces the redirect branch. Then peek the bag to assert the toast
        // was queued there (and a redirect, not a live re-render, was returned).
        self::bootKernel();
        $container = self::getContainer();

        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $registry = $container->get(ResourceRegistry::class);
        self::assertInstanceOf(ResourceRegistry::class, $registry);
        $writer = $container->get(ArrayDataWriter::class);
        self::assertInstanceOf(ArrayDataWriter::class, $writer);
        $realProvider = $container->get(DataProviderInterface::class);
        self::assertInstanceOf(DataProviderInterface::class, $realProvider);

        // The in-memory writer is a separate store from the provider, so a delete is
        // not reflected by re-reading the provider. Decorate it so find() reports the
        // record gone once the writer has deleted it — exactly the production case a
        // shared backend gives, which drives RecordActions' redirect-to-list branch.
        $provider = new class($realProvider, $writer) implements DataProviderInterface {
            public function __construct(
                private readonly DataProviderInterface $inner,
                private readonly ArrayDataWriter $writer,
            ) {
            }

            public function fetch(string $entityClass, DataQuery $query): iterable
            {
                return $this->inner->fetch($entityClass, $query);
            }

            public function count(string $entityClass, DataQuery $query): int
            {
                return $this->inner->count($entityClass, $query);
            }

            public function find(string $entityClass, int|string $id, array $filters = [], string $idField = 'id'): ?object
            {
                if ([] !== $this->writer->deleted) {
                    return null;
                }

                return $this->inner->find($entityClass, $id, $filters, $idField);
            }
        };

        $actions = new RecordActions($registry, $provider, $writer, new Notifier($requestStack));
        $actions->mount('view-tag', '3', '/admin');

        $actions->confirmingAction = 'delete';
        $actions->confirmingId = '3';
        $response = $actions->confirmAction();

        // Deleting the only record sends the user back to the list …
        self::assertNotNull($response);
        self::assertTrue($response->isRedirect());

        // … with the success toast queued on the flash channel, not emitted live.
        $flashed = $session->getFlashBag()->peek(Notifier::FLASH_KEY);
        self::assertCount(1, $flashed);
        self::assertIsArray($flashed[0]);
        self::assertSame('Deleted', $flashed[0]['title']);
        self::assertSame('success', $flashed[0]['status']);
    }
}
