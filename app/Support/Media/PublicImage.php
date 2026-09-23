<?php

namespace App\Support\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * What public views receive for an image: conversion URLs only, never the original
 * (docs/17). Built by the presenter from a Media whose conversions exist.
 */
final readonly class PublicImage
{
    /**
     * @param  array<string, string>  $urls  conversion name => absolute URL
     */
    private function __construct(
        public int $id,
        public string $alt,
        private array $urls,
    ) {}

    /**
     * Null while the conversions have not been generated yet (queued) so that nothing
     * but the placeholder is shown meanwhile.
     */
    public static function fromMedia(Media $media, string ...$conversions): ?self
    {
        $urls = [];

        foreach ($conversions as $conversion) {
            if (! $media->hasGeneratedConversion($conversion)) {
                return null;
            }

            $urls[$conversion] = $media->getFullUrl($conversion);
        }

        $alt = $media->getCustomProperty('alt');

        return new self($media->getKey(), is_string($alt) ? $alt : '', $urls);
    }

    public function url(string $conversion): string
    {
        return $this->urls[$conversion] ?? throw new \InvalidArgumentException("Conversion [{$conversion}] was not requested for this image.");
    }

    /**
     * "thumb.webp 400w, card.webp 800w" for the given conversions, using the configured widths.
     */
    public function srcset(string ...$conversions): string
    {
        $parts = [];

        foreach ($conversions as $conversion) {
            $width = (int) config("avytra.media.conversions.{$conversion}.width");
            $parts[] = $this->url($conversion)." {$width}w";
        }

        return implode(', ', $parts);
    }

    public function width(string $conversion): int
    {
        return (int) config("avytra.media.conversions.{$conversion}.width");
    }

    public function height(string $conversion): int
    {
        return (int) config("avytra.media.conversions.{$conversion}.height");
    }
}
