<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PanelTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testDashboardRenders(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Dashboard');
        // The registered resource appears in the sidebar navigation.
        self::assertSelectorTextContains('aside', 'Tags');
    }

    public function testResourceListRendersTableWithRows(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/admin/tag');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type="search"]');
        self::assertSelectorTextContains('thead', 'Name');
        self::assertSelectorTextContains('thead', 'Slug');

        // The in-memory provider seeded 12 tags; the first page shows the default 10.
        self::assertCount(10, $crawler->filter('tbody tr'));
        self::assertSelectorTextContains('tbody', 'Tag 01');
        self::assertSelectorTextContains('.atrium-datatable', '12 results');
    }

    public function testUnknownResourceReturns404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/does-not-exist');

        self::assertResponseStatusCodeSame(404);
    }

    public function testNavigationRespectsAccessRegistrationBadgeAndSort(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        $aside = $crawler->filter('aside');
        // shouldRegisterNavigation() === false: reachable but never in the menu.
        self::assertStringNotContainsString('Unlisted tags', $aside->text());
        // canAccess() === false: hidden from the menu too.
        self::assertStringNotContainsString('Forbidden tags', $aside->text());
        // A navigation badge renders next to its entry.
        self::assertStringContainsString('Badged tags', $aside->text());
        self::assertSelectorTextContains('aside', '7');

        // The dashboards group comes first; within the resources group,
        // getNavigationSort(-5) puts the badged entry ahead of the unweighted ones.
        $labels = $crawler->filter('aside nav a span.flex-1')->each(static fn ($node): string => trim($node->text()));
        self::assertNotEmpty($labels);
        self::assertSame('Insights', $labels[0]);
        self::assertSame('Badged tags', $labels[1]);
    }

    public function testUnlistedResourceIsStillReachable(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/unlisted-tag');

        // Hidden from the menu, but its pages work.
        self::assertResponseIsSuccessful();
    }

    public function testForbiddenResourceReturns403(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/forbidden-tag');

        self::assertResponseStatusCodeSame(403);
    }
}
