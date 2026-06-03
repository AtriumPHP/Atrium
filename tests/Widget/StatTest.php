<?php

declare(strict_types=1);

namespace Atrium\Tests\Widget;

use Atrium\Widget\Stat;
use PHPUnit\Framework\TestCase;

final class StatTest extends TestCase
{
    public function testMakeCastsValueToString(): void
    {
        self::assertSame('1240', Stat::make('Orders', 1240)->getValue());
        self::assertSame('3.5', Stat::make('Avg', 3.5)->getValue());
        self::assertSame('$12k', Stat::make('Revenue', '$12k')->getValue());
    }

    public function testDefaults(): void
    {
        $stat = Stat::make('Orders', 5);

        self::assertSame('Orders', $stat->getLabel());
        self::assertSame('gray', $stat->getColor());
        self::assertNull($stat->getDescription());
        self::assertNull($stat->getDescriptionIcon());
        self::assertNull($stat->getUrl());
    }

    public function testFluentSetters(): void
    {
        $stat = Stat::make('Revenue', '$12k')
            ->description('+12%')
            ->descriptionIcon('arrow-trending-up')
            ->color('success')
            ->url('/admin/orders');

        self::assertSame('+12%', $stat->getDescription());
        self::assertSame('arrow-trending-up', $stat->getDescriptionIcon());
        self::assertSame('success', $stat->getColor());
        self::assertSame('/admin/orders', $stat->getUrl());
    }
}
