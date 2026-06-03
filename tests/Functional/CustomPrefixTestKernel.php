<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * The standard test panel mounted under a non-default `path_prefix`. Used to
 * prove that a single config value relocates both where the panel matches
 * requests and the URLs it generates.
 */
final class CustomPrefixTestKernel extends AtriumTestKernel
{
    public const PREFIX = '/manage';

    protected function configureContainer(ContainerConfigurator $container): void
    {
        parent::configureContainer($container);

        $container->extension('atrium', ['path_prefix' => self::PREFIX]);
    }

    /**
     * Namespace the cache so the relocated routes do not collide with the
     * default kernel's compiled container/router (both boot in `test`).
     */
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/atrium-tests/cache/custom-prefix/'.$this->environment;
    }
}
