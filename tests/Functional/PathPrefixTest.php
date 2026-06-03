<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Tests\Fixtures\Resource\ViewTagResource;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The `path_prefix` config key is authoritative: it moves both route matching
 * and link generation together, so they can never drift apart.
 */
final class PathPrefixTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return CustomPrefixTestKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
        ViewTagResource::reset();
    }

    public function testRoutesMatchUnderTheConfiguredPrefix(): void
    {
        $client = self::createClient();
        $client->request('GET', '/manage/view-tag/3');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Tag 03');
    }

    public function testTheDefaultPrefixNoLongerMatches(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin/view-tag/3');

        self::assertResponseStatusCodeSame(404);
    }

    public function testGeneratedLinksUseTheConfiguredPrefix(): void
    {
        $client = self::createClient();
        $client->request('GET', '/manage/view-tag/3');

        // The header edit link is generated from the same prefix that matched.
        self::assertSelectorExists('a[href="/manage/view-tag/3/edit"]');
    }
}
