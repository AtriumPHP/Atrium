<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NestedBreadcrumbTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testBreadcrumbShowsTheParentTrail(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/project/1/task/1');

        self::assertResponseIsSuccessful();
        $nav = $crawler->filter('nav[aria-label="Breadcrumb"]');
        self::assertCount(1, $nav);
        $text = $nav->text();
        self::assertStringContainsString('Projects', $text); // parent resource label
        self::assertStringContainsString('Alpha', $text);     // parent record title (project 1 name)
        self::assertStringContainsString('Tasks', $text);     // child resource label
        // Parent record links to its view page.
        self::assertStringContainsString('/admin/project/1', $nav->html());
    }

    public function testFlatPageHasNoBreadcrumb(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/admin/project/1');

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('nav[aria-label="Breadcrumb"]'));
    }
}
