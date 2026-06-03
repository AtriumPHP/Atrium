<?php

declare(strict_types=1);

namespace Atrium\View;

/**
 * Renders the record value as an **image** (a photo, logo or avatar). The state is
 * the image URL — dotted paths and `getStateUsing()` work as on any entry. When
 * there is no URL, a {@see defaultImageUrl()} is used if set, else the entry's
 * `placeholder()`.
 *
 * ```php
 * ImageEntry::make('avatar')->circular()->imageSize(48)
 *     ->defaultImageUrl('/img/avatar-fallback.png');
 * ```
 */
final class ImageEntry extends Entry
{
    private ?int $width = null;
    private ?int $height = null;
    private bool $circular = false;
    private ?string $defaultImageUrl = null;

    public function imageWidth(int $width): static
    {
        $this->width = max(0, $width);

        return $this;
    }

    public function imageHeight(int $height): static
    {
        $this->height = max(0, $height);

        return $this;
    }

    public function imageSize(int $size): static
    {
        $this->width = max(0, $size);
        $this->height = max(0, $size);

        return $this;
    }

    public function circular(bool $circular = true): static
    {
        $this->circular = $circular;

        return $this;
    }

    public function square(bool $square = true): static
    {
        $this->circular = !$square;

        return $this;
    }

    public function defaultImageUrl(string $url): static
    {
        $this->defaultImageUrl = $url;

        return $this;
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/view/image.html.twig';
    }

    protected function viewExtras(mixed $state, object $record): array
    {
        $formatted = $this->applyFormatter($state, $record);
        $url = \is_string($formatted) && '' !== trim($formatted) ? $formatted : $this->defaultImageUrl;
        $src = self::safeImageUrl($url);

        return [
            'src' => $src,
            'isEmpty' => null === $src,
            'alt' => $this->getLabel(),
            'circular' => $this->circular,
            'width' => $this->width,
            'height' => $this->height,
        ];
    }

    /**
     * Permit only an http(s), root-relative, or protocol-relative image source;
     * reject anything carrying another scheme (e.g. `javascript:`) so it can never
     * become an executable `src`. A `data:` URI is dropped too — entries display
     * stored URLs, not inline blobs.
     */
    private static function safeImageUrl(?string $url): ?string
    {
        if (null === $url || '' === trim($url)) {
            return null;
        }

        $candidate = ltrim($url);

        if (preg_match('#^(?:https?:|//|/|\.)#i', $candidate)) {
            return $url;
        }

        return preg_match('#^[a-z][a-z0-9+.\-]*:#i', $candidate) ? null : $url;
    }
}
