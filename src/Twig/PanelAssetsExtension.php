<?php

declare(strict_types=1);

namespace Atrium\Twig;

use Symfony\Component\Asset\Packages;
use Symfony\Component\AssetMapper\ImportMap\ImportMapRenderer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Renders the panel's own assets (stylesheet + JS entrypoint) without
 * hard-coupling the bundle to AssetMapper.
 *
 * The stylesheet is the bundle's precompiled, shipped CSS (exposed through the
 * `atrium` AssetMapper path); the JS entrypoint is the host app's importmap.
 * If AssetMapper is absent both functions are no-ops and the host app is
 * expected to provide assets by overriding the layout's blocks.
 *
 * @internal
 */
final class PanelAssetsExtension extends AbstractExtension
{
    public function __construct(
        private readonly ?ImportMapRenderer $importMapRenderer = null,
        private readonly ?Packages $packages = null,
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('atrium_importmap', $this->renderImportmap(...), ['is_safe' => ['html']]),
            new TwigFunction('atrium_stylesheet', $this->renderStylesheet(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param string|list<string> $entrypoint
     */
    public function renderImportmap(string|array $entrypoint = 'app'): string
    {
        return $this->importMapRenderer?->render($entrypoint) ?? '';
    }

    public function renderStylesheet(string $path = 'atrium/atrium.css'): string
    {
        if (null === $this->packages) {
            return '';
        }

        try {
            $url = $this->packages->getUrl($path);
        } catch (\Throwable) {
            return '';
        }

        return \sprintf('<link rel="stylesheet" href="%s">', htmlspecialchars($url, \ENT_QUOTES, 'UTF-8'));
    }
}
