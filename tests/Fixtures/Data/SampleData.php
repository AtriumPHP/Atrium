<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Data;

use Atrium\DataProvider\ArrayDataProvider;
use Atrium\Tests\Fixtures\Entity\Tag;

/**
 * Seeds an in-memory {@see ArrayDataProvider} with sample Tag records for the
 * functional tests, so the panel can be exercised without a database.
 */
final class SampleData
{
    public static function provider(): ArrayDataProvider
    {
        $tags = [];
        for ($i = 1; $i <= 12; ++$i) {
            $tags[] = new Tag($i, \sprintf('Tag %02d', $i), \sprintf('tag-%02d', $i));
        }

        return new ArrayDataProvider([Tag::class => $tags]);
    }
}
