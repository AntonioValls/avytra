<?php

namespace App\Enums;

/**
 * Image collections of a business (docs/17): logo and cover hold one file, the gallery many.
 */
enum MediaCollection: string
{
    case Logo = 'logo';
    case Cover = 'cover';
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Logo => __('Logo'),
            self::Cover => __('Cover image'),
            self::Gallery => __('Gallery'),
        };
    }

    public function isSingleFile(): bool
    {
        return $this !== self::Gallery;
    }

    /**
     * Conversions generated for files of this collection (names from config avytra.media.conversions).
     *
     * @return list<string>
     */
    public function conversions(): array
    {
        return match ($this) {
            self::Logo => ['logo'],
            self::Cover => ['thumb', 'card', 'detail', 'og'],
            self::Gallery => ['thumb', 'card', 'detail'],
        };
    }
}
