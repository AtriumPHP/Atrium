<?php

declare(strict_types=1);

namespace Atrium\Content;

use Atrium\Layout\Component;
use Atrium\Layout\Concern\HasColumnSpan;
use Atrium\Layout\Concern\HasGrow;

/**
 * Base class for content components — static building blocks (Text, Image, lists)
 * that insert arbitrary content into a schema.
 *
 * Unlike a field, a content component carries no form state: it has no name, is
 * never hydrated or validated, and {@see \Atrium\Form\Schema::getFields()} skips
 * it. Unlike a layout container, it is a leaf with no children. It can still be
 * placed in a grid/flex (it implements {@see Component} and gets column-span /
 * grow placement), which is what makes it useful for headings, instructions and
 * callouts inside a form.
 */
abstract class ContentComponent implements Component
{
    use HasColumnSpan;
    use HasGrow;

    public function getChildComponents(): array
    {
        return [];
    }
}
