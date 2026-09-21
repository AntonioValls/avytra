<?php

namespace App\Support\Seo;

/**
 * Metadata for a public page. Views never build <meta> tags by hand; they pass
 * an instance of this class to the public layout.
 */
final readonly class PageMeta
{
    /**
     * @param  list<array<string, mixed>>  $jsonLd
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $robots = null,
        public ?string $ogImage = null,
        public string $ogType = 'website',
        public array $jsonLd = [],
    ) {}

    public static function make(?string $title = null, ?string $description = null): self
    {
        return new self(title: $title, description: $description);
    }

    public function fullTitle(): string
    {
        $appName = config('app.name');

        return filled($this->title) ? "{$this->title} · {$appName}" : $appName;
    }

    public function resolvedDescription(): string
    {
        return $this->description ?? __('Businesses for sale or transfer, with clear data and confirmed availability.');
    }

    public function resolvedCanonical(): string
    {
        return $this->canonical ?? url()->current();
    }

    public function resolvedOgImage(): string
    {
        return $this->ogImage ?? asset('og-default.png');
    }
}
