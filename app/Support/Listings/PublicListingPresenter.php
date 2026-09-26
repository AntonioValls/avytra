<?php

namespace App\Support\Listings;

use App\Enums\BusinessType;
use App\Enums\ContactMethod;
use App\Enums\Disclosure;
use App\Enums\EmployeeRange;
use App\Enums\FinancialMetric;
use App\Enums\LegalForm;
use App\Enums\ListingStatus;
use App\Enums\LocationVisibility;
use App\Enums\LogisticsType;
use App\Enums\OnlineBusinessType;
use App\Enums\OperationType;
use App\Enums\PriceDisclosure;
use App\Enums\TechnologyPlatform;
use App\Enums\WebsiteVisibility;
use App\Models\Listing;
use App\Models\ListingFinancialMetric;
use App\Models\Location;
use App\Support\Location\PublicPoint;
use App\Support\Media\PublicImage;
use App\Support\Seo\PageMeta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The only thing public views, cards, JSON-LD and the sitemap may read (docs/16).
 *
 * It receives the Listing and exposes attributes already filtered by every visibility
 * setting: location_visibility, price_disclosure, disclosure per metric, website_visibility,
 * show_legal_form. Private coordinates, address (unless exact), postal code, legal name and
 * anything about the owner's account never come out of here. Phone and WhatsApp are
 * available only through sensitiveChannel(), which the contact box calls after a click;
 * the contact email never comes out at all (messages are relayed, ADR-019).
 */
final class PublicListingPresenter
{
    /**
     * Relations every public query must eager load before handing listings to the presenter.
     *
     * @var list<string>
     */
    public const array RELATIONS = [
        'business.category',
        'business.subcategory',
        'business.location.province',
        'business.location.municipality',
        'business.onlineProfile',
        'business.media',
        'operationTypes',
        'financialMetrics',
    ];

    private function __construct(
        private Listing $listing,
        private ?string $titleOverride = null,
    ) {
        $listing->loadMissing(self::RELATIONS);
    }

    public static function for(Listing $listing): self
    {
        return new self($listing);
    }

    /**
     * The wizard preview shows the title being typed before it is saved.
     */
    public function withTitle(?string $title): self
    {
        return new self($this->listing, filled($title) ? trim((string) $title) : null);
    }

    /* ------------------------------------------------------------ identity */

    public function id(): int
    {
        return $this->listing->id;
    }

    public function slug(): ?string
    {
        return $this->listing->slug;
    }

    public function url(): ?string
    {
        return $this->listing->slug === null ? null : route('listings.show', $this->listing->slug);
    }

    public function title(): string
    {
        return $this->titleOverride ?? $this->listing->title ?? __('Untitled listing');
    }

    public function tagline(): ?string
    {
        return $this->listing->business->tagline;
    }

    public function businessName(): string
    {
        return $this->listing->business->name;
    }

    public function businessType(): BusinessType
    {
        return $this->listing->business->business_type;
    }

    public function categoryName(): ?string
    {
        return $this->listing->business->category?->name;
    }

    public function categorySlug(): ?string
    {
        return $this->listing->business->category?->slug;
    }

    public function subcategoryName(): ?string
    {
        return $this->listing->business->subcategory?->name;
    }

    /**
     * @return list<OperationType>
     */
    public function operationTypes(): array
    {
        $primary = $this->listing->primary_operation_type;
        $types = $this->listing->offeredOperationTypes();

        if ($primary === null || ! in_array($primary, $types, true)) {
            return $types;
        }

        return [$primary, ...array_values(array_filter($types, fn (OperationType $type): bool => $type !== $primary))];
    }

    public function primaryOperationType(): ?OperationType
    {
        return $this->listing->primary_operation_type ?? $this->operationTypes()[0] ?? null;
    }

    public function stakePercent(): ?int
    {
        return $this->primaryOperationType()?->allowsStake() === true ? $this->listing->stake_percent : null;
    }

    public function operationNotes(): ?string
    {
        return $this->listing->operation_notes;
    }

    /* -------------------------------------------------------------- status */

    public function isSold(): bool
    {
        return $this->listing->status === ListingStatus::Sold;
    }

    public function soldAt(): ?CarbonImmutable
    {
        return $this->listing->sold_at;
    }

    public function isPubliclyVisible(): bool
    {
        return $this->listing->isPubliclyVisible();
    }

    public function publishedAt(): ?CarbonImmutable
    {
        return $this->listing->published_at;
    }

    /* ----------------------------------------------------------- freshness */

    public function confirmedDaysAgo(): ?int
    {
        return $this->listing->daysSinceConfirmation();
    }

    /**
     * Past the first reminder threshold: still public, but the buyer deserves to know.
     */
    public function isPendingRenewal(): bool
    {
        return $this->listing->needsConfirmation();
    }

    public function freshnessText(): ?string
    {
        $days = $this->confirmedDaysAgo();

        if ($days === null || $this->isSold()) {
            return null;
        }

        $text = match (true) {
            $days <= 0 => __('Availability confirmed today'),
            $days === 1 => __('Availability confirmed yesterday'),
            default => __('Availability confirmed :days days ago', ['days' => $days]),
        };

        return $this->isPendingRenewal() ? $text.' · '.__('pending renewal') : $text;
    }

    /* ------------------------------------------------------------ location */

    private function location(): ?Location
    {
        if (! $this->businessType()->requiresLocation()) {
            return null;
        }

        return $this->listing->business->location;
    }

    public function locationVisibility(): ?LocationVisibility
    {
        return $this->location()?->effectiveVisibility();
    }

    public function provinceName(): ?string
    {
        return $this->location()?->province->name;
    }

    public function provinceSlug(): ?string
    {
        return $this->location()?->province->slug;
    }

    /**
     * Null when the seller hides the location: then only the province is public.
     */
    public function municipalityName(): ?string
    {
        $location = $this->location();

        if ($location === null || $location->effectiveVisibility() === LocationVisibility::Hidden) {
            return null;
        }

        return $location->municipality?->name;
    }

    /**
     * The street address is public only with "exact" visibility.
     */
    public function addressLine(): ?string
    {
        $location = $this->location();

        if ($location === null || $location->effectiveVisibility() !== LocationVisibility::Exact) {
            return null;
        }

        return $location->address_line;
    }

    /**
     * Derived public point for the map (Phase 5). Never the private coordinates.
     */
    public function publicPoint(): ?PublicPoint
    {
        $location = $this->location();

        if ($location === null || $location->effectiveVisibility() === LocationVisibility::Hidden) {
            return null;
        }

        $point = $location->publicPoint();

        return $point->isNone() ? null : $point;
    }

    /**
     * What the explore map receives for this listing: the derived public point (never the
     * private coordinates), its radius, title, URL and location text for the popup.
     *
     * @return array{lat: float, lng: float, radius: int|null, title: string, url: string|null, text: string}|null
     */
    public function mapPoint(): ?array
    {
        $point = $this->publicPoint();

        if ($point === null || $point->latitude === null || $point->longitude === null) {
            return null;
        }

        return [
            'lat' => $point->latitude,
            'lng' => $point->longitude,
            'radius' => $point->radiusM,
            'title' => $this->title(),
            'url' => $this->url(),
            'text' => $this->locationText(),
        ];
    }

    /**
     * "Castellón de la Plana, Castellón", "Provincia de Castellón" or "Online".
     */
    public function locationText(): string
    {
        $province = $this->provinceName();

        if ($province === null) {
            return __('Online');
        }

        $municipality = $this->municipalityName();

        if ($municipality === null || $municipality === $province) {
            return $municipality === null ? __('Province of :province', ['province' => $province]) : $province;
        }

        return "{$municipality}, {$province}";
    }

    /**
     * Sentence under the location block explaining how precise the public location is.
     */
    public function locationExplanation(): ?string
    {
        $visibility = $this->locationVisibility();

        if ($visibility === null) {
            return null;
        }

        if ($visibility === LocationVisibility::Exact) {
            return $this->addressLine();
        }

        return match ($visibility) {
            LocationVisibility::Approximate => __('Approximate area. The exact address is provided on contact.'),
            LocationVisibility::CityOnly => __('Municipality only. The exact address is provided on contact.'),
            LocationVisibility::Hidden => __('The seller shows only the province.'),
        };
    }

    public function isHybrid(): bool
    {
        return $this->businessType() === BusinessType::Hybrid;
    }

    /* -------------------------------------------------------------- images */

    /**
     * The cover, or the first gallery image when no cover was uploaded. Null while the
     * conversions are pending or when there are no images: views show the brand placeholder.
     */
    public function coverImage(): ?PublicImage
    {
        $business = $this->listing->business;
        $media = $business->cover() ?? $business->galleryImages()->first();

        return $media === null ? null : PublicImage::fromMedia($media, 'thumb', 'card', 'detail');
    }

    /**
     * Every image for the lightbox: the cover first, then the gallery in its order.
     * Only images whose conversions exist; the original file is never exposed.
     *
     * @return list<PublicImage>
     */
    public function galleryImages(): array
    {
        $business = $this->listing->business;
        $images = [];

        $cover = $business->cover();

        if ($cover !== null) {
            $images[] = PublicImage::fromMedia($cover, 'thumb', 'card', 'detail');
        }

        foreach ($business->galleryImages() as $media) {
            $images[] = PublicImage::fromMedia($media, 'thumb', 'card', 'detail');
        }

        return array_values(array_filter($images));
    }

    public function logoImage(): ?PublicImage
    {
        $logo = $this->listing->business->logo();

        return $logo === null ? null : PublicImage::fromMedia($logo, 'logo');
    }

    /**
     * Absolute URL of the 1200×630 Open Graph conversion of the cover, if it exists.
     */
    public function ogImageUrl(): ?string
    {
        $cover = $this->listing->business->cover();

        return $cover === null ? null : PublicImage::fromMedia($cover, 'og')?->url('og');
    }

    /* --------------------------------------------------------------- price */

    public function priceText(): string
    {
        return PriceFormatter::forListing($this->listing);
    }

    public function hasPrice(): bool
    {
        return $this->listing->price_disclosure !== PriceDisclosure::OnRequest;
    }

    public function isPriceNegotiable(): bool
    {
        return $this->listing->is_price_negotiable === true;
    }

    /* ------------------------------------------------------------- content */

    public function description(): ?string
    {
        return $this->listing->business->description;
    }

    /**
     * @return list<string>
     */
    public function highlights(): array
    {
        return array_values(array_filter($this->listing->highlights ?? [], fn (string $line): bool => trim($line) !== ''));
    }

    public function reasonForSale(): ?string
    {
        return $this->listing->reason_for_sale;
    }

    /**
     * Only what the seller stated; null booleans are not shown.
     *
     * @return list<array{label: string, included: bool}>
     */
    public function includedItems(): array
    {
        $items = [
            'includes_stock' => __('Stock'),
            'includes_equipment' => __('Equipment and machinery'),
            'includes_property' => __('Premises (owned)'),
            'includes_staff' => __('Team stays on'),
            'includes_intellectual_property' => __('Brand and intellectual property'),
        ];

        $result = [];

        foreach ($items as $column => $label) {
            $value = $this->listing->getAttribute($column);

            if (is_bool($value)) {
                $result[] = ['label' => $label, 'included' => $value];
            }
        }

        return $result;
    }

    public function includedAssetsNotes(): ?string
    {
        return $this->listing->included_assets_notes;
    }

    public function premisesIsRented(): ?bool
    {
        return $this->listing->premises_is_rented;
    }

    public function employeeRange(): ?EmployeeRange
    {
        return $this->listing->business->employee_range;
    }

    public function foundedYear(): ?int
    {
        return $this->listing->business->founded_year;
    }

    public function legalForm(): ?LegalForm
    {
        $business = $this->listing->business;

        return $business->show_legal_form ? $business->legal_form : null;
    }

    public function websiteUrl(): ?string
    {
        $business = $this->listing->business;

        return $business->website_visibility === WebsiteVisibility::Public ? $business->website_url : null;
    }

    /* ---------------------------------------------------------- financials */

    /**
     * Metrics the seller chose to show, in catalogue order. Hidden ones are absent.
     *
     * @return list<array{metric: FinancialMetric, text: string, year: int|null}>
     */
    public function financialMetrics(): array
    {
        $byMetric = $this->listing->financialMetrics->keyBy(fn (ListingFinancialMetric $row): string => $row->metric->value);
        $result = [];

        foreach (FinancialMetric::cases() as $metric) {
            $row = $byMetric->get($metric->value);

            if (! $row instanceof ListingFinancialMetric) {
                continue;
            }

            $text = PriceFormatter::forMetric($row);

            if ($text === null) {
                continue;
            }

            $result[] = ['metric' => $metric, 'text' => $text, 'year' => $row->period_year];
        }

        return $result;
    }

    /**
     * A single metric as text, or null when absent, hidden or on request (cards show figures only).
     */
    public function disclosedFigure(FinancialMetric $metric): ?string
    {
        foreach ($this->listing->financialMetrics as $row) {
            if ($row->metric !== $metric || in_array($row->disclosure, [Disclosure::Hidden, Disclosure::OnRequest], true)) {
                continue;
            }

            return PriceFormatter::forMetric($row);
        }

        return null;
    }

    /* -------------------------------------------------------------- online */

    /**
     * The online section, already filtered: visits per disclosure, web and social
     * profiles only when the website is public.
     *
     * @return array{
     *     type: OnlineBusinessType,
     *     platform: string|null,
     *     domain_registered_year: int|null,
     *     monthly_visits: string|null,
     *     registered_users: int|null,
     *     active_customers: int|null,
     *     monthly_orders: int|null,
     *     recurring_revenue_percent: int|null,
     *     acquisition_channels: list<string>,
     *     marketplaces: list<string>,
     *     has_stock: bool|null,
     *     logistics: LogisticsType|null,
     *     team_included: bool|null,
     *     website_url: string|null,
     *     social_profiles: list<array{network: string, url: string}>
     * }|null
     */
    public function onlineProfile(): ?array
    {
        if (! $this->businessType()->requiresOnlineProfile()) {
            return null;
        }

        $profile = $this->listing->business->onlineProfile;

        if ($profile === null) {
            return null;
        }

        $platform = $profile->technology_platform === null
            ? null
            : ($profile->technology_platform === TechnologyPlatform::Other && filled($profile->technology_platform_other)
                ? $profile->technology_platform_other
                : $profile->technology_platform->label());

        $visits = $this->monthlyVisitsText($profile->monthly_visits_disclosure, $profile->monthly_visits);

        $websitePublic = $this->websiteUrl() !== null;

        return [
            'type' => $profile->online_business_type,
            'platform' => $platform,
            'domain_registered_year' => $profile->domain_registered_year,
            'monthly_visits' => $visits,
            'registered_users' => $profile->registered_users,
            'active_customers' => $profile->active_customers,
            'monthly_orders' => $profile->monthly_orders,
            'recurring_revenue_percent' => $profile->recurring_revenue_percent,
            'acquisition_channels' => $profile->acquisition_channels ?? [],
            'marketplaces' => $profile->sells_on_marketplaces ?? [],
            'has_stock' => $profile->has_stock,
            'logistics' => $profile->logistics_type,
            'team_included' => $profile->team_included,
            'website_url' => $this->websiteUrl(),
            'social_profiles' => $websitePublic ? ($profile->social_profiles ?? []) : [],
        ];
    }

    private function monthlyVisitsText(Disclosure $disclosure, ?int $visits): ?string
    {
        if ($disclosure === Disclosure::Hidden) {
            return null;
        }

        if ($disclosure === Disclosure::OnRequest) {
            return PriceFormatter::onRequest();
        }

        if ($visits === null) {
            return null;
        }

        $count = number_format($visits, 0, ',', '.');

        return $disclosure === Disclosure::Exact
            ? __(':count visits per month', ['count' => $count])
            : __('About :count visits per month', ['count' => $count]);
    }

    /* ------------------------------------------------------------- contact */

    public function contactName(): ?string
    {
        return $this->listing->contact_name;
    }

    public function preferredContactMethod(): ?ContactMethod
    {
        return $this->listing->preferred_contact_method;
    }

    /**
     * Channels with a value, preferred first. The email channel is always present: it is
     * relayed through the contact form and goes to the owner's inbox when the listing has
     * no contact email (ADR-019).
     *
     * @return list<ContactMethod>
     */
    public function contactMethods(): array
    {
        $filled = array_values(array_filter(
            ContactMethod::cases(),
            fn (ContactMethod $method): bool => self::isRelayChannel($method) || filled($this->listing->getAttribute($method->channelColumn())),
        ));

        $preferred = $this->preferredContactMethod();

        if ($preferred === null || ! in_array($preferred, $filled, true)) {
            return $filled;
        }

        return [$preferred, ...array_values(array_filter($filled, fn (ContactMethod $method): bool => $method !== $preferred))];
    }

    public function hasContact(): bool
    {
        return $this->contactMethods() !== [];
    }

    public function contactNotes(): ?string
    {
        return $this->listing->contact_notes;
    }

    /**
     * Phone and WhatsApp are revealed only after a click (docs/12).
     */
    public static function isSensitiveChannel(ContactMethod $method): bool
    {
        return in_array($method, [ContactMethod::Phone, ContactMethod::Whatsapp], true);
    }

    /**
     * The email is never printed: buyers write through the relay form (ADR-019).
     */
    public static function isRelayChannel(ContactMethod $method): bool
    {
        return $method === ContactMethod::Email;
    }

    /**
     * Value of a channel that may be printed in the initial HTML (website, form, free text).
     */
    public function publicChannel(ContactMethod $method): ?string
    {
        if (self::isSensitiveChannel($method) || self::isRelayChannel($method)) {
            return null;
        }

        $value = $this->listing->getAttribute($method->channelColumn());

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Value of phone or WhatsApp. Callers must have applied the reveal rate limit.
     */
    public function sensitiveChannel(ContactMethod $method): ?string
    {
        if (! self::isSensitiveChannel($method)) {
            return null;
        }

        $value = $this->listing->getAttribute($method->channelColumn());

        return is_string($value) && $value !== '' ? $value : null;
    }

    /* ----------------------------------------------------------------- seo */

    public function metaTitle(): string
    {
        return Str::limit($this->title(), 48, '…').' · '.$this->priceText();
    }

    public function metaDescription(): string
    {
        $tagline = $this->tagline();

        if (filled($tagline)) {
            return Str::limit((string) $tagline, 155, '…');
        }

        return Str::limit(trim((string) preg_replace('/\s+/', ' ', (string) $this->description())), 155, '…');
    }

    public function robots(): ?string
    {
        if ($this->listing->status === ListingStatus::Published) {
            return null;
        }

        if ($this->isSold() && $this->isPubliclyVisible()) {
            return null;
        }

        return 'noindex,follow';
    }

    public function pageMeta(): PageMeta
    {
        return new PageMeta(
            title: $this->metaTitle(),
            description: $this->metaDescription(),
            canonical: $this->url(),
            robots: $this->robots(),
            ogImage: $this->ogImageUrl(),
            ogType: 'article',
            jsonLd: $this->url() === null ? [] : [$this->offerJsonLd(), $this->breadcrumbJsonLd()],
        );
    }

    /**
     * schema.org Offer built only from public data (docs/15-seo.md).
     *
     * @return array<string, mixed>
     */
    public function offerJsonLd(): array
    {
        $listing = $this->listing;

        $organisation = [
            '@type' => $this->locationVisibility() === LocationVisibility::Exact ? 'LocalBusiness' : 'Organization',
            'name' => $this->businessName(),
        ];

        if ($this->provinceName() !== null) {
            $address = [
                '@type' => 'PostalAddress',
                'addressRegion' => $this->provinceName(),
                'addressCountry' => 'ES',
            ];

            if ($this->municipalityName() !== null) {
                $address['addressLocality'] = $this->municipalityName();
            }

            if ($this->addressLine() !== null) {
                $address['streetAddress'] = $this->addressLine();
            }

            $organisation['address'] = $address;

            $point = $this->publicPoint();

            if ($this->locationVisibility() === LocationVisibility::Exact && $point !== null && $point->latitude !== null) {
                $organisation['geo'] = [
                    '@type' => 'GeoCoordinates',
                    'latitude' => $point->latitude,
                    'longitude' => $point->longitude,
                ];
            }
        }

        $offer = [
            '@context' => 'https://schema.org',
            '@type' => 'Offer',
            'name' => $this->title(),
            'description' => $this->metaDescription(),
            'url' => $this->url(),
            'availability' => $this->isSold() ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
            'itemOffered' => $organisation,
        ];

        $cover = $this->coverImage();

        if ($cover !== null) {
            $offer['image'] = $cover->url('detail');
        }

        if ($listing->price_disclosure === PriceDisclosure::Exact && $listing->asking_price !== null) {
            $offer['price'] = $listing->asking_price;
            $offer['priceCurrency'] = $listing->currency;
        } elseif ($listing->price_disclosure === PriceDisclosure::Range && $listing->asking_price_min !== null && $listing->asking_price_max !== null) {
            $offer['priceSpecification'] = [
                '@type' => 'PriceSpecification',
                'minPrice' => $listing->asking_price_min,
                'maxPrice' => $listing->asking_price_max,
                'priceCurrency' => $listing->currency,
            ];
        }

        if ($listing->published_at !== null) {
            $offer['datePosted'] = $listing->published_at->toIso8601String();
        }

        if ($listing->next_confirmation_at !== null && ! $this->isSold()) {
            $offer['validThrough'] = $listing->next_confirmation_at->toIso8601String();
        }

        return $offer;
    }

    /**
     * @return array<string, mixed>
     */
    public function breadcrumbJsonLd(): array
    {
        $items = [
            ['name' => __('Home'), 'item' => route('home')],
            ['name' => __('Businesses'), 'item' => route('listings.index')],
        ];

        if ($this->categorySlug() !== null) {
            $items[] = ['name' => (string) $this->categoryName(), 'item' => route('categories.show', $this->categorySlug())];
        }

        $items[] = ['name' => $this->title(), 'item' => $this->url()];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['item'],
            ], $items, array_keys($items)),
        ];
    }
}
