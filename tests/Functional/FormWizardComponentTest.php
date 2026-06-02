<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\DataProvider\ArrayDataWriter;
use Atrium\Tests\Fixtures\Entity\Tag;
use Atrium\Twig\Components\WizardForm;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/**
 * SCH-10: the WizardForm renders a step header + all panels (inactive hidden),
 * gates Next on the current step's validation, navigates back freely, focuses
 * the errored step on a failed save, and persists on submit.
 */
final class FormWizardComponentTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testRendersStepHeaderAndPanels(): void
    {
        $html = $this->createLiveComponent('Atrium:WizardForm', ['resource' => 'wizard-tag'])
            ->render()->toString();

        // Step nav + a Next action; no global "Create" submit (the wizard owns it).
        self::assertStringContainsString('data-live-action-param="nextStep"', $html);
        self::assertStringContainsString('Identity', $html);
        self::assertStringContainsString('Details', $html);
        // Both panels are in the DOM (inputs from step 1 and step 2) …
        self::assertStringContainsString('atrium_slug', $html);
        self::assertStringContainsString('atrium_name', $html);
        // … and the first step's footer offers Next, not a Create/Submit button.
        self::assertStringContainsString('Next', $html);
        self::assertStringNotContainsString('Create', $html);
    }

    public function testNextGatesOnCurrentStepValidation(): void
    {
        $component = $this->createLiveComponent('Atrium:WizardForm', ['resource' => 'wizard-tag']);
        $wizardId = $this->wizardId($component);

        // slug (step 1) is required and empty → Next is blocked, stays on step 0.
        $component->call('nextStep', ['wizard' => $wizardId]);
        $form = $this->form($component);
        self::assertArrayHasKey('slug', $form->errors);
        self::assertSame(0, $form->currentSteps[$wizardId] ?? 0);

        // Fill slug → Next advances to step 1.
        $component->set('formData', ['slug' => 'ok'])->call('nextStep', ['wizard' => $wizardId]);
        self::assertSame(1, $this->form($component)->currentSteps[$wizardId]);
    }

    public function testBackReturnsToThePreviousStep(): void
    {
        $component = $this->createLiveComponent('Atrium:WizardForm', ['resource' => 'wizard-tag']);
        $wizardId = $this->wizardId($component);

        $component->set('formData', ['slug' => 'ok'])->call('nextStep', ['wizard' => $wizardId]);
        self::assertSame(1, $this->form($component)->currentSteps[$wizardId]);

        $component->call('previousStep', ['wizard' => $wizardId]);
        self::assertSame(0, $this->form($component)->currentSteps[$wizardId]);
    }

    public function testFailedSaveFocusesTheStepWithTheError(): void
    {
        $component = $this->createLiveComponent('Atrium:WizardForm', ['resource' => 'wizard-tag']);
        $wizardId = $this->wizardId($component);

        // name (required) lives in step 2; submit with it empty.
        $component->set('formData', ['slug' => 'ok'])->call('save');

        $form = $this->form($component);
        self::assertArrayHasKey('name', $form->errors);
        self::assertSame(1, $form->currentSteps[$wizardId] ?? 0);
    }

    public function testSubmitPersistsThroughTheWriter(): void
    {
        $component = $this->createLiveComponent('Atrium:WizardForm', ['resource' => 'wizard-tag']);

        $component
            ->set('formData', ['slug' => 'wiz', 'name' => 'Wizardberry', 'active' => true])
            ->call('save');

        self::assertTrue($this->form($component)->saved);

        $writer = self::getContainer()->get(ArrayDataWriter::class);
        self::assertInstanceOf(ArrayDataWriter::class, $writer);
        $tags = $writer->records[Tag::class] ?? [];
        self::assertCount(1, $tags);
        self::assertInstanceOf(Tag::class, $tags[0]);
        self::assertSame('Wizardberry', $tags[0]->name);
    }

    private function wizardId(TestLiveComponent $component): string
    {
        $html = $component->render()->toString();
        if (1 !== preg_match('/data-live-wizard-param="([^"]+)"/', $html, $m)) {
            self::fail('expected a wizard id in the rendered output');
        }

        return $m[1];
    }

    private function form(TestLiveComponent $component): WizardForm
    {
        $instance = $component->component();
        self::assertInstanceOf(WizardForm::class, $instance);

        return $instance;
    }
}
