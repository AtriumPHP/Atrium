<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardPageTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testRootShowsDefaultWelcomeWhenNoRootDashboard(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Dashboard');
        self::assertSelectorTextContains('body', 'Welcome to');
    }

    public function testDashboardRouteRendersItsWidgets(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/insights');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Insights');
        // The Section heading and both widgets render through the layout tree.
        self::assertSelectorTextContains('body', 'Key metrics');
        self::assertSelectorTextContains('body', 'Renders');
        self::assertSelectorTextContains('body', 'Sales per month');
        self::assertSelectorExists('canvas');
    }

    public function testForbiddenDashboardReturns403(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/forbidden');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDashboardsAppearInNavigationByAccess(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        $aside = $crawler->filter('aside')->text();
        // Accessible, navigation-registered dashboard is listed…
        self::assertStringContainsString('Insights', $aside);
        // …an inaccessible one is not.
        self::assertStringNotContainsString('Forbidden', $aside);
    }

    public function testUnknownSlugReturns404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/no-such-thing');

        self::assertResponseStatusCodeSame(404);
    }
}
