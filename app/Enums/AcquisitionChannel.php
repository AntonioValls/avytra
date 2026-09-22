<?php

namespace App\Enums;

enum AcquisitionChannel: string
{
    case Seo = 'seo';
    case Sem = 'sem';
    case SocialOrganic = 'social_organic';
    case SocialAds = 'social_ads';
    case Email = 'email';
    case Marketplaces = 'marketplaces';
    case Affiliates = 'affiliates';
    case Referrals = 'referrals';
    case Offline = 'offline';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Seo => __('SEO'),
            self::Sem => __('Paid search (SEM)'),
            self::SocialOrganic => __('Organic social media'),
            self::SocialAds => __('Social media ads'),
            self::Email => __('Email marketing'),
            self::Marketplaces => __('Marketplaces'),
            self::Affiliates => __('Affiliates'),
            self::Referrals => __('Referrals'),
            self::Offline => __('Offline'),
            self::Other => __('Other channel'),
        };
    }
}
