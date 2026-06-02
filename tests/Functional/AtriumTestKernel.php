<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\AtriumBundle;
use Atrium\DataProvider\ArrayDataProvider;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\Tests\Fixtures\Data\SampleData;
use Atrium\Tests\Fixtures\Resource\TagResource;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

/**
 * A minimal but realistic panel app used by the functional tests: it boots the
 * Atrium bundle alongside the Twig/Live component bundles and AssetMapper, binds
 * an in-memory data provider, and registers one resource.
 */
final class AtriumTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new StimulusBundle(),
            new TwigComponentBundle(),
            new LiveComponentBundle(),
            new AtriumBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__.'/app';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'atrium-test',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'asset_mapper' => [
                'paths' => ['%kernel.project_dir%/assets'],
            ],
        ]);

        $container->extension('twig_component', [
            'defaults' => [],
            'anonymous_template_directory' => 'components/',
        ]);

        $services = $container->services();

        $services->set(TagResource::class)
            ->autoconfigure()
            ->autowire();

        // Bind the backend-agnostic read layer to an in-memory provider.
        $services->set(ArrayDataProvider::class)
            ->factory([SampleData::class, 'provider']);
        $services->alias(DataProviderInterface::class, ArrayDataProvider::class);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 2).'/config/routes.php');
        $routes->import('@LiveComponentBundle/config/routes.php')->prefix('/_components');
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/atrium-tests/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/atrium-tests/log';
    }
}
