<?php

declare(strict_types=1);

namespace Atrium\Content;

/**
 * A static image with optional explicit dimensions and horizontal alignment.
 */
final class Image extends ContentComponent
{
    private ?int $width = null;

    private ?int $height = null;

    /** @var 'start'|'center'|'end' */
    private string $alignment = 'start';

    public function __construct(
        private readonly string $url,
        private readonly string $alt = '',
    ) {
    }

    public static function make(string $url, string $alt = ''): self
    {
        return new self($url, $alt);
    }

    public function imageWidth(int $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function imageHeight(int $height): self
    {
        $this->height = $height;

        return $this;
    }

    public function imageSize(int $size): self
    {
        $this->width = $size;
        $this->height = $size;

        return $this;
    }

    public function alignStart(): self
    {
        $this->alignment = 'start';

        return $this;
    }

    public function alignCenter(): self
    {
        $this->alignment = 'center';

        return $this;
    }

    public function alignEnd(): self
    {
        $this->alignment = 'end';

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getAlt(): string
    {
        return $this->alt;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getAlignmentClass(): string
    {
        return match ($this->alignment) {
            'center' => 'mx-auto',
            'end' => 'ml-auto',
            default => 'mr-auto',
        };
    }

    public function getTemplate(): string
    {
        return '@Atrium/components/content/image.html.twig';
    }
}
