<?php

namespace App\Support\Location;

/**
 * What the public map may show: a point with an optional radius (null radius = exact pin),
 * or nothing at all.
 */
final readonly class PublicPoint
{
    public function __construct(
        public ?float $latitude,
        public ?float $longitude,
        public ?int $radiusM,
    ) {}

    public static function none(): self
    {
        return new self(null, null, null);
    }

    public function isNone(): bool
    {
        return $this->latitude === null || $this->longitude === null;
    }

    public function coordinates(): ?Coordinates
    {
        if ($this->isNone()) {
            return null;
        }

        return new Coordinates((float) $this->latitude, (float) $this->longitude);
    }
}
