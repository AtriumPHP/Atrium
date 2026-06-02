<?php

declare(strict_types=1);

namespace Atrium\Tests\Fixtures\Doctrine;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

/**
 * Builds a throwaway, in-memory (SQLite) EntityManager mapped against the test
 * fixture entities. Used by the data-layer tests so they exercise the real
 * Doctrine stack rather than a mock.
 */
final class EntityManagerFactory
{
    public static function create(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfig(
            paths: [\dirname(__DIR__).'/Entity'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);

        $connection = DriverManager::getConnection(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            $config,
        );

        return new EntityManager($connection, $config);
    }
}
