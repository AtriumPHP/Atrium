<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PagesTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testCreatePageRendersAnEmptyForm(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/tag/new');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'New Tag');
        self::assertSelectorExists('form input#atrium_name');
        self::assertSelectorTextContains('button[data-live-action-param="save"]', 'Create');
    }

    public function testEditPagePrefillsTheForm(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/admin/tag/3/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Edit Tag');
        self::assertSame('Tag 03', $crawler->filter('#atrium_name')->attr('value'));
        self::assertSelectorTextContains('button[data-live-action-param="save"]', 'Save changes');
    }

    public function testEditUnknownEntityReturns404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/tag/9999/edit');

        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateUnknownResourceReturns404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/nope/new');

        self::assertResponseStatusCodeSame(404);
    }
}
