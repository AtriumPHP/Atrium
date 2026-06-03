<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The list screen renders header and footer widget bands around the table (LW),
 * each through the independent widget host, and renders neither band for a
 * resource that declares no widgets.
 */
final class ListWidgetsTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testListScreenRendersHeaderAndFooterWidgetBands(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/list-widget-tag');

        self::assertResponseIsSuccessful();
        // The table is still there.
        self::assertSelectorExists('table');
        // Header band: the stats widget (CounterStatsWidget) rendered above.
        self::assertSelectorTextContains('body', '+1 each render');
        // Footer band: the chart widget heading rendered below.
        self::assertSelectorTextContains('body', 'Sales per month');
    }

    public function testHeaderBandSitsAboveTheTableAndFooterBelow(): void
    {
        $client = self::createClient();
        $html = (string) $client->request('GET', '/admin/list-widget-tag')->html();

        $header = strpos($html, '+1 each render');
        $table = strpos($html, '<table');
        $footer = strpos($html, 'Sales per month');

        self::assertNotFalse($header);
        self::assertNotFalse($table);
        self::assertNotFalse($footer);
        self::assertLessThan($table, $header, 'The header widget band must render above the table.');
        self::assertGreaterThan($table, $footer, 'The footer widget band must render below the table.');
    }

    public function testResourceWithoutWidgetsRendersNoBands(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/tag');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'Sales per month');
        self::assertSelectorTextNotContains('body', '+1 each render');
    }
}
