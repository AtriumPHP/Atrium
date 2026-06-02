<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Resource;

use Atrium\Form\Field\TextField;
use Atrium\Form\Schema;
use Atrium\Resource\AdminResource;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Cross-field validation fixture (FRM-12): `confirmName` must match `name`
 * (`same()`), proving the form evaluates comparison rules against the full
 * submitted state.
 */
final class ConfirmTagResource extends AdminResource
{
    public function getEntityClass(): string
    {
        return Tag::class;
    }

    public function getSlug(): string
    {
        return 'confirm-tag';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->fields([
            TextField::make('name')->required(),
            TextField::make('confirmName')->dehydrated(false)->same('name'),
        ]);
    }
}
