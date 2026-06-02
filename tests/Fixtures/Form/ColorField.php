<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Form;

use Atrium\Form\Field\Field;

/**
 * Example third-party field type: it ships its own widget template from a
 * non-Atrium namespace, proving custom fields need no change to the renderer.
 */
final class ColorField extends Field
{
    public function getType(): string
    {
        return 'color';
    }

    public function getWidgetTemplate(): string
    {
        return '@Acme/fields/color.html.twig';
    }
}
