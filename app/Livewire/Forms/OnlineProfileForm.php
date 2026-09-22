<?php

namespace App\Livewire\Forms;

use App\Enums\AcquisitionChannel;
use App\Enums\Disclosure;
use App\Enums\LogisticsType;
use App\Enums\OnlineBusinessType;
use App\Enums\TechnologyPlatform;
use App\Models\OnlineProfile;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Online-specific data of an online or hybrid business (docs/11-online-businesses.md).
 * Only the online business type is mandatory.
 */
class OnlineProfileForm extends Form
{
    public ?string $online_business_type = null;

    public ?string $technology_platform = null;

    public string $technology_platform_other = '';

    public ?int $domain_registered_year = null;

    public ?int $monthly_visits = null;

    public string $monthly_visits_disclosure = Disclosure::Exact->value;

    public ?int $registered_users = null;

    public ?int $active_customers = null;

    public ?int $monthly_orders = null;

    public ?int $recurring_revenue_percent = null;

    /** @var list<string> */
    public array $acquisition_channels = [];

    /** @var list<array{network: string, url: string}> */
    public array $social_profiles = [];

    public string $sells_on_marketplaces = '';

    public ?bool $has_stock = null;

    public ?string $logistics_type = null;

    public ?bool $team_included = null;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'online_business_type' => ['required', Rule::enum(OnlineBusinessType::class)],
            'technology_platform' => ['nullable', Rule::enum(TechnologyPlatform::class)],
            'technology_platform_other' => ['nullable', 'string', 'max:80', Rule::requiredIf($this->technology_platform === TechnologyPlatform::Other->value)],
            'domain_registered_year' => ['nullable', 'integer', 'min:1985', 'max:'.now()->year],
            'monthly_visits' => ['nullable', 'integer', 'min:0'],
            'monthly_visits_disclosure' => ['required', Rule::enum(Disclosure::class)],
            'registered_users' => ['nullable', 'integer', 'min:0'],
            'active_customers' => ['nullable', 'integer', 'min:0'],
            'monthly_orders' => ['nullable', 'integer', 'min:0'],
            'recurring_revenue_percent' => ['nullable', 'integer', 'between:0,100'],
            'acquisition_channels' => ['array'],
            'acquisition_channels.*' => [Rule::enum(AcquisitionChannel::class)],
            'social_profiles' => ['array', 'max:10'],
            'social_profiles.*.network' => ['required_with:social_profiles.*.url', 'nullable', 'string', 'max:40'],
            'social_profiles.*.url' => ['required_with:social_profiles.*.network', 'nullable', 'string', 'url:http,https', 'max:255'],
            'sells_on_marketplaces' => ['nullable', 'string', 'max:255'],
            'has_stock' => ['nullable', 'boolean'],
            'logistics_type' => ['nullable', Rule::enum(LogisticsType::class)],
            'team_included' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'online_business_type' => __('online business type'),
            'technology_platform' => __('technology platform'),
            'technology_platform_other' => __('other platform'),
            'domain_registered_year' => __('domain registration year'),
            'monthly_visits' => __('monthly visits'),
            'monthly_visits_disclosure' => __('visits disclosure'),
            'registered_users' => __('registered users'),
            'active_customers' => __('active customers'),
            'monthly_orders' => __('monthly orders'),
            'recurring_revenue_percent' => __('recurring revenue percentage'),
            'acquisition_channels' => __('acquisition channels'),
            'social_profiles.*.network' => __('social network'),
            'social_profiles.*.url' => __('profile URL'),
            'sells_on_marketplaces' => __('marketplaces'),
            'has_stock' => __('stock'),
            'logistics_type' => __('logistics'),
            'team_included' => __('team included'),
        ];
    }

    public function fillFromOnlineProfile(OnlineProfile $profile): void
    {
        $this->online_business_type = $profile->online_business_type->value;
        $this->technology_platform = $profile->technology_platform?->value;
        $this->technology_platform_other = $profile->technology_platform_other ?? '';
        $this->domain_registered_year = $profile->domain_registered_year;
        $this->monthly_visits = $profile->monthly_visits;
        $this->monthly_visits_disclosure = $profile->monthly_visits_disclosure->value;
        $this->registered_users = $profile->registered_users;
        $this->active_customers = $profile->active_customers;
        $this->monthly_orders = $profile->monthly_orders;
        $this->recurring_revenue_percent = $profile->recurring_revenue_percent;
        $this->acquisition_channels = $profile->acquisition_channels ?? [];
        $this->social_profiles = $profile->social_profiles ?? [];
        $this->sells_on_marketplaces = implode(', ', $profile->sells_on_marketplaces ?? []);
        $this->has_stock = $profile->has_stock;
        $this->logistics_type = $profile->logistics_type?->value;
        $this->team_included = $profile->team_included;
    }

    public function addSocialProfile(): void
    {
        $this->social_profiles[] = ['network' => '', 'url' => ''];
    }

    public function removeSocialProfile(int $index): void
    {
        array_splice($this->social_profiles, $index, 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $marketplaces = array_values(array_filter(array_map(
            fn (string $name): string => trim($name),
            explode(',', $this->sells_on_marketplaces),
        ), fn (string $name): bool => $name !== ''));

        $socialProfiles = array_values(array_filter(
            $this->social_profiles,
            fn (array $profile): bool => trim($profile['network']) !== '' && trim($profile['url']) !== '',
        ));

        return [
            'online_business_type' => $this->online_business_type,
            'technology_platform' => $this->technology_platform,
            'technology_platform_other' => $this->technology_platform === TechnologyPlatform::Other->value
                ? $this->nullable($this->technology_platform_other)
                : null,
            'domain_registered_year' => $this->domain_registered_year,
            'monthly_visits' => $this->monthly_visits,
            'monthly_visits_disclosure' => $this->monthly_visits_disclosure,
            'registered_users' => $this->registered_users,
            'active_customers' => $this->active_customers,
            'monthly_orders' => $this->monthly_orders,
            'recurring_revenue_percent' => $this->recurring_revenue_percent,
            'acquisition_channels' => $this->acquisition_channels,
            'social_profiles' => $socialProfiles === [] ? null : $socialProfiles,
            'sells_on_marketplaces' => $marketplaces === [] ? null : $marketplaces,
            'has_stock' => $this->has_stock,
            'logistics_type' => $this->logistics_type,
            'team_included' => $this->team_included,
        ];
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
