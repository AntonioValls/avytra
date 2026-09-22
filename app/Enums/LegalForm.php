<?php

namespace App\Enums;

enum LegalForm: string
{
    case SoleTrader = 'sole_trader';
    case Sl = 'sl';
    case Sa = 'sa';
    case Slu = 'slu';
    case Cooperative = 'cooperative';
    case CommunityOfGoods = 'community_of_goods';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SoleTrader => __('Sole trader'),
            self::Sl => __('Limited company (SL)'),
            self::Sa => __('Public limited company (SA)'),
            self::Slu => __('Single-member limited company (SLU)'),
            self::Cooperative => __('Cooperative'),
            self::CommunityOfGoods => __('Community of goods'),
            self::Other => __('Other legal form'),
        };
    }
}
