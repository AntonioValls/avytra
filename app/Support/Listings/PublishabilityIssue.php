<?php

namespace App\Support\Listings;

/**
 * One thing that prevents a listing from being published, with the wizard step that fixes it.
 */
final readonly class PublishabilityIssue
{
    public function __construct(
        public int $step,
        public string $message,
    ) {}
}
