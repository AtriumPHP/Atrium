<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Tests\Fixtures\Resource\ViewTagResource;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RecordViewTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
        ViewTagResource::reset();
    }

    public function testViewScreenRendersTheEntrySchema(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/view-tag/3');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'View');
        self::assertSelectorTextContains('body', 'Tag 03');
        self::assertSelectorTextContains('body', 'tag-03');
        // active=false on record 3, formatted by the entry.
        self::assertSelectorTextContains('body', 'Inactive');
    }

    public function testViewScreenRendersARepeatableBlockPerItem(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/view-tag/3');

        self::assertResponseIsSuccessful();
        // Each item is bound as the record for the nested entries, so both
        // synthesised variants render their own label.
        self::assertSelectorTextContains('body', 'Small');
        self::assertSelectorTextContains('body', 'Large');
    }

    public function testViewScreenShowsAnEditLinkInTheHeader(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/view-tag/3');

        self::assertSelectorExists('a[href="/admin/view-tag/3/edit"]');
    }

    public function testViewScreenFallsBackToFormFieldsWhenNoViewSchema(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/fallback-view-tag/3');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Tag 03');
        self::assertSelectorTextContains('body', 'tag-03');
    }

    public function testResourceWithoutViewPageReturns404(): void
    {
        $client = self::createClient();
        // TagResource registers no 'view' page, so the bare record URL has no screen.
        $client->request('GET', '/admin/tag/3');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownRecordReturns404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/view-tag/9999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testForbiddenWhenViewIsDenied(): void
    {
        ViewTagResource::$allowView = false;

        $client = self::createClient();
        $client->request('GET', '/admin/view-tag/3');

        self::assertResponseStatusCodeSame(403);
    }

    public function testListRowsLinkToTheViewScreen(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/view-tag');

        self::assertResponseIsSuccessful();
        // The row is a link to the bare record URL (the View screen) via the
        // stretched overlay anchor in the first cell.
        self::assertSelectorExists('tbody a[href="/admin/view-tag/3"]');
    }

    public function testListRowsAreNotClickableWithoutAViewScreen(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/tag');

        self::assertResponseIsSuccessful();
        // TagResource has no 'view' page, so the default 'view' target suppresses
        // the row link (the bare record URL is not linked).
        self::assertSelectorNotExists('tbody a[href="/admin/tag/3"]');
    }

    public function testCreateRouteStillWinsOverTheViewRoute(): void
    {
        $client = self::createClient();
        // `/admin/view-tag/new` must route to create, not view with id="new".
        $client->request('GET', '/admin/view-tag/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'New');
    }
}
