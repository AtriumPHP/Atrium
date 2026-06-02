<?php

declare(strict_types=1);

namespace Atrium\Tests\Layout;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Layout\Step;
use Atrium\Layout\Wizard;
use Atrium\Tests\Fixtures\Resource\TabsTagResource;
use Atrium\Tests\Fixtures\Resource\WizardTagResource;
use PHPUnit\Framework\TestCase;

/**
 * Wizard/Step are layout containers (SCH-10): they build a tree, expose a stable
 * id for the current-step state and flatten to their leaf fields. A resource
 * whose schema contains a Wizard renders through the WizardForm component.
 */
final class WizardTest extends TestCase
{
    public function testStepIdAndMetadata(): void
    {
        $step = Step::make('Account Details')->icon('user')->description('Who you are');

        self::assertSame('account-details', $step->getId());
        self::assertSame('user', $step->getIcon());
        self::assertSame('Who you are', $step->getDescription());
    }

    public function testWizardExposesStepsAndAStableId(): void
    {
        $wizard = Wizard::make()->steps([
            Step::make('One'),
            Step::make('Two'),
        ]);

        self::assertCount(2, $wizard->getSteps());
        self::assertContainsOnlyInstancesOf(Step::class, $wizard->getSteps());
        self::assertStringStartsWith('wizard-', $wizard->getId());
        self::assertSame('settings', Wizard::make()->id('settings')->steps([Step::make('A')])->getId());
    }

    public function testFieldsFlattenThroughSteps(): void
    {
        $schema = (new Schema())->components([
            Wizard::make()->steps([
                Step::make('One')->schema([TextField::make('title')]),
                Step::make('Two')->schema([TextField::make('slug'), TextField::make('author')]),
            ]),
        ]);

        $names = array_map(static fn ($field): string => $field->getName(), $schema->getFields());
        self::assertSame(['title', 'slug', 'author'], $names);
    }

    public function testResourcePicksFormComponentBySchema(): void
    {
        self::assertSame('Atrium:WizardForm', (new WizardTagResource())->getFormComponentName());
        // Tabs are not a wizard — they use the plain Form.
        self::assertSame('Atrium:Form', (new TabsTagResource())->getFormComponentName());
    }
}
