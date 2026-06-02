<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\CheckboxField;
use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Layout\Step;
use Atrium\Layout\Wizard;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Wizard fixture (SCH-10): a two-step form. `name` (required) lives in the
 * second step, so Next from step one advances, while a required field is in the
 * first step (`slug`) lets us prove Next gates on the current step's validation.
 */
final class WizardTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'wizard-tag';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make()->steps([
                Step::make('Identity')->description('The basics')->schema([
                    TextField::make('slug')->required(),
                ]),
                Step::make('Details')->icon('cube')->columns(2)->schema([
                    TextField::make('name')->required(),
                    CheckboxField::make('active'),
                ]),
            ]),
        ]);
    }
}
