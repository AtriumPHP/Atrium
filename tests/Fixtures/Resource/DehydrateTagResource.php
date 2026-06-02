<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Fixture for FRM-14: `slug` is shown and validated but `dehydrated(false)`, so
 * it must not be written to the entity on save.
 */
final class DehydrateTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'dehydrate-tag';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([
            TextField::make('name')->required(),
            TextField::make('slug')->dehydrated(false),
        ]);
    }
}
