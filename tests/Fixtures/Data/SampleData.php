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
        $kinds = ['fruit', 'tool', 'animal'];
        $tags = [];
        for ($i = 1; $i <= 12; ++$i) {
            $tags[] = new Tag(
                id: $i,
                name: \sprintf('Tag %02d', $i),
                slug: \sprintf('tag-%02d', $i),
                active: 0 === $i % 2,
                kind: $kinds[($i - 1) % 3],
            );
        }

        return new ArrayDataProvider([Tag::class => $tags]);
    }
}
