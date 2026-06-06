<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\RelationManagers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * The RelationManagers host (REL-08): one relation renders as a section, several
 * render as a tab strip that mounts only the active manager.
 */
final class RelationManagersTabsTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    private function host(string $resource): TestLiveComponent
    {
        return $this->createLiveComponent('Atrium:RelationManagers', [
            'resource' => $resource,
            'parentId' => '1',
            'pathPrefix' => '/admin',
        ]);
    }

    private function state(TestLiveComponent $component): RelationManagers
    {
        $host = $component->component();
        self::assertInstanceOf(RelationManagers::class, $host);

        return $host;
    }

    public function testRendersTabStripAndMountsOnlyTheActiveManager(): void
    {
        $component = $this->host('post-multi');
        $html = $component->render()->toString();

        // Two tab labels…
        self::assertStringContainsString('Comments', $html);
        self::assertStringContainsString('Tags', $html);
        // …and the first relation (comments) is active by default.
        self::assertStringContainsString('Great post', $html);
        self::assertStringNotContainsString('Tag 01', $html);

        $component->call('selectTab', ['relation' => 'tags']);
        self::assertSame('tags', $this->state($component)->getActiveRelation());
        $html = $component->render()->toString();
        self::assertStringContainsString('Tag 01', $html);       // tags manager now mounted
        self::assertStringNotContainsString('Great post', $html);
    }

    public function testSelectTabIgnoresAnUnknownRelation(): void
    {
        $component = $this->host('post-multi');
        $component->call('selectTab', ['relation' => 'nope']);

        // Falls back to the first relation.
        self::assertSame('comments', $this->state($component)->getActiveRelation());
    }

    public function testSingleRelationRendersASectionWithoutATabStrip(): void
    {
        $html = $this->host('post')->render()->toString();

        self::assertStringContainsString('Great post', $html);
        self::assertStringNotContainsString('role="tablist"', $html);
    }
}
