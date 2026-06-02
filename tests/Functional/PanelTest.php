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
}
