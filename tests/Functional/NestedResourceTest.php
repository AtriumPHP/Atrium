<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NestedResourceTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testNestedViewRendersAChildScopedToItsParent(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/1/task/1');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Design'); // task 1's title
    }

    public function testCrossParentViewIs404(): void
    {
        $client = static::createClient();
        // Task 1 belongs to project 1; requesting it under project 2 must 404.
        $client->request('GET', '/admin/project/2/task/1');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUnknownParentRecordIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/999/task/1');

        self::assertResponseStatusCodeSame(404);
    }

    public function testUndeclaredNestingIs404(): void
    {
        $client = static::createClient();
        // 'tag-rel' is a registered resource but is NOT nested under 'project'.
        $client->request('GET', '/admin/project/1/tag-rel/1');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNestedEditRendersScopedRecord(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/1/task/2/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }

    public function testCrossParentEditIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/2/task/1/edit'); // task 1 is under project 1

        self::assertResponseStatusCodeSame(404);
    }

    public function testNestedIndexListsOnlyThisParentsChildren(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/project/1/task');

        self::assertResponseIsSuccessful();
        $body = $crawler->filter('body')->text();
        self::assertStringContainsString('Design', $body);  // project 1
        self::assertStringContainsString('Build', $body);   // project 1
        self::assertStringNotContainsString('Ship', $body); // project 2 — excluded
    }

    public function testNestedIndexRowLinksAreFiveSegment(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/project/1/task');

        self::assertResponseIsSuccessful();
        // A row link to a task view must carry the parent segment.
        self::assertStringContainsString('/admin/project/1/task/1', $crawler->html());
    }

    public function testNestedCreateRenders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/project/1/task/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form');
    }
}
