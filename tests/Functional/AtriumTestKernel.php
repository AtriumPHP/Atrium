<?php

declare(strict_types=1);

namespace Atrium\Tests\Functional;

use Atrium\AtriumBundle;
use Atrium\DataProvider\ArrayDataProvider;
use Atrium\DataProvider\ArrayDataWriter;
use Atrium\DataProvider\ArrayRelationProvider;
use Atrium\DataProvider\DataProviderInterface;
use Atrium\DataProvider\DataWriterInterface;
use Atrium\DataProvider\RelationDataProvider;
use Atrium\Tests\Fixtures\Dashboard\ForbiddenDashboard;
use Atrium\Tests\Fixtures\Dashboard\InsightsDashboard;
use Atrium\Tests\Fixtures\Data\SampleData;
use Atrium\Tests\Fixtures\Resource\ActionsTagResource;
use Atrium\Tests\Fixtures\Resource\BadgedTagResource;
use Atrium\Tests\Fixtures\Resource\CommentRelResource;
use Atrium\Tests\Fixtures\Resource\ConfirmTagResource;
use Atrium\Tests\Fixtures\Resource\DehydrateTagResource;
use Atrium\Tests\Fixtures\Resource\DenyAssociatePostResource;
use Atrium\Tests\Fixtures\Resource\DenyDissociatePostResource;
use Atrium\Tests\Fixtures\Resource\FilteredTagResource;
use Atrium\Tests\Fixtures\Resource\ForbiddenTagResource;
use Atrium\Tests\Fixtures\Resource\HeaderActionTagResource;
use Atrium\Tests\Fixtures\Resource\HookedTagResource;
use Atrium\Tests\Fixtures\Resource\LayoutTagResource;
use Atrium\Tests\Fixtures\Resource\ListWidgetTagResource;
use Atrium\Tests\Fixtures\Resource\PaginatedTagResource;
use Atrium\Tests\Fixtures\Resource\PostMultiRelResource;
use Atrium\Tests\Fixtures\Resource\PostRelResource;
use Atrium\Tests\Fixtures\Resource\PostTagsResource;
use Atrium\Tests\Fixtures\Resource\ProjectResource;
use Atrium\Tests\Fixtures\Resource\RestrictedPostTagsResource;
use Atrium\Tests\Fixtures\Resource\RowUrlTagResource;
use Atrium\Tests\Fixtures\Resource\ScopedTagResource;
use Atrium\Tests\Fixtures\Resource\TabsTagResource;
use Atrium\Tests\Fixtures\Resource\TagRelResource;
use Atrium\Tests\Fixtures\Resource\TagResource;
use Atrium\Tests\Fixtures\Resource\TaskResource;
use Atrium\Tests\Fixtures\Resource\UnlistedTagResource;
use Atrium\Tests\Fixtures\Resource\ViewFallbackTagResource;
use Atrium\Tests\Fixtures\Resource\ViewTagResource;
use Atrium\Tests\Fixtures\Resource\ViewWidgetTagResource;
use Atrium\Tests\Fixtures\Resource\WizardTagResource;
use Atrium\Tests\Fixtures\Widget\CounterStatsWidget;
use Atrium\Tests\Fixtures\Widget\HiddenWidget;
use Atrium\Tests\Fixtures\Widget\ParamsStatsWidget;
use Atrium\Tests\Fixtures\Widget\SalesChartWidget;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\Chartjs\ChartjsBundle;
use Symfony\UX\Icons\UXIconsBundle;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

/**
 * A minimal but realistic panel app used by the functional tests: it boots the
 * Atrium bundle alongside the Twig/Live component bundles and AssetMapper, binds
 * an in-memory data provider, and registers one resource.
 */
class AtriumTestKernel extends Kernel
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
            new ChartjsBundle(),
            new UXIconsBundle(),
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
            'validation' => ['enabled' => true],
            'asset_mapper' => [
                'paths' => ['%kernel.project_dir%/assets'],
            ],
        ]);

        $container->extension('twig_component', [
            'defaults' => [],
            'anonymous_template_directory' => 'components/',
        ]);

        $services = $container->services();

        // Several functional tests deliberately trigger 403/404 responses to assert
        // them. Symfony's error handler logs each one, which the bundled fallback
        // logger writes to stderr as noisy "Uncaught PHP Exception" lines. Swap in a
        // NullLogger so the test output (and CI logs) stay clean — the assertions on
        // the responses still hold.
        $services->set('logger', NullLogger::class);

        $services->set(TagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(LayoutTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(DehydrateTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ConfirmTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(TabsTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(WizardTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(PaginatedTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(FilteredTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(HookedTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ScopedTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(BadgedTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(UnlistedTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ForbiddenTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ActionsTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(HeaderActionTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ListWidgetTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ViewTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ViewFallbackTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ViewWidgetTagResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(RowUrlTagResource::class)
            ->autoconfigure()
            ->autowire();

        // Relation fixtures: a parent (Post) with a one-to-many `comments`
        // relation to the child resource (Comment).
        $services->set(PostRelResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(CommentRelResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(DenyDissociatePostResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(DenyAssociatePostResource::class)
            ->autoconfigure()
            ->autowire();

        // Many-to-many relation fixtures: a parent (Post) with a `tags` relation
        // through the post_tag pivot to the child resource (Tag).
        $services->set(TagRelResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(PostTagsResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(RestrictedPostTagsResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(PostMultiRelResource::class)
            ->autoconfigure()
            ->autowire();

        // Nested-resource fixtures: a parent (Project) whose `tasks` one-to-many
        // targets a nested child resource (Task) declaring parent().
        $services->set(ProjectResource::class)
            ->autoconfigure()
            ->autowire();

        $services->set(TaskResource::class)
            ->autoconfigure()
            ->autowire();

        // Widget fixtures (tagged atrium.widget via autoconfiguration).
        $services->set(CounterStatsWidget::class)
            ->autoconfigure()
            ->autowire();

        $services->set(HiddenWidget::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ParamsStatsWidget::class)
            ->autoconfigure()
            ->autowire();

        $services->set(SalesChartWidget::class)
            ->autoconfigure()
            ->autowire();

        // Dashboard fixtures (tagged atrium.dashboard via autoconfiguration).
        $services->set(InsightsDashboard::class)
            ->autoconfigure()
            ->autowire();

        $services->set(ForbiddenDashboard::class)
            ->autoconfigure()
            ->autowire();

        // Bind the backend-agnostic read/write layer to in-memory adapters. One
        // SampleData instance per boot backs both providers, so reads and relation
        // queries share the same fixture objects (a single in-memory backend).
        $services->set(SampleData::class);

        $services->set(ArrayDataProvider::class)
            ->factory([service(SampleData::class), 'provider']);
        $services->alias(DataProviderInterface::class, ArrayDataProvider::class);

        $services->set(ArrayDataWriter::class)->public();
        $services->alias(DataWriterInterface::class, ArrayDataWriter::class);

        $services->set(ArrayRelationProvider::class)
            ->factory([service(SampleData::class), 'relationProvider'])
            ->public();
        $services->alias(RelationDataProvider::class, ArrayRelationProvider::class);
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
