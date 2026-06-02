<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\Twig\Components\Form;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * SCH-10: a tabbed form renders its tab nav + all panels (inactive hidden),
 * switches the active tab server-side, keeps every input in the DOM across the
 * switch, and focuses the tab holding a validation error on a failed save.
 */
final class FormTabsComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testTabsRenderNavAndPanelsWithFirstActive(): void
    {
        $html = $this->createLiveComponent('Atrium:Form', ['resource' => 'tabs-tag'])
            ->render()->toString();

        // Two tab buttons wired to the selectTab action.
        self::assertStringContainsString('data-live-action-param="selectTab"', $html);
        self::assertSame(2, substr_count($html, 'role="tab"'));
        self::assertStringContainsString('Main', $html);
        self::assertStringContainsString('Details', $html);

        // Every panel is rendered: inputs from BOTH tabs are present in the DOM …
        self::assertStringContainsString('atrium_slug', $html);
        self::assertStringContainsString('atrium_name', $html);
        // … but the second (inactive) panel is hidden.
        self::assertStringContainsString('role="tabpanel"', $html);
        self::assertStringContainsString('hidden', $html);
    }

    public function testSelectTabSwitchesTheActivePanel(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'tabs-tag']);

        $tabsId = array_key_first($this->tabsIds($component));
        self::assertIsString($tabsId);

        $component->call('selectTab', ['tabs' => $tabsId, 'tab' => 'details']);

        self::assertSame('details', $this->form($component)->activeTabs[$tabsId]);
    }

    public function testFailedSaveFocusesTheTabWithTheError(): void
    {
        $component = $this->createLiveComponent('Atrium:Form', ['resource' => 'tabs-tag']);

        // `name` (required) lives in the second tab; default active is the first.
        $component->call('save');

        $form = $this->form($component);
        self::assertArrayHasKey('name', $form->errors);
        self::assertContains('details', $form->activeTabs, 'the tab holding the error should become active');
    }

    /**
     * @return array<string, string>
     */
    private function tabsIds(TestLiveComponent $component): array
    {
        // Trigger one selectTab with a throwaway then read the keyed state back,
        // or derive the id from the schema via a render. Simplest: render and
        // pull the data-live-tabs-param value.
        $html = $component->render()->toString();
        preg_match('/data-live-tabs-param="([^"]+)"/', $html, $m);

        return [] === $m ? [] : [$m[1] => $m[1]];
    }

    private function form(TestLiveComponent $component): Form
    {
        $instance = $component->component();
        self::assertInstanceOf(Form::class, $instance);

        return $instance;
    }
}
