<?php

namespace App\Enums;

/**
 * What the seller offers. A listing may combine several (ADR-015); one of them is primary.
 */
enum OperationType: string
{
    case FullSale = 'full_sale';
    case Transfer = 'transfer';
    case ShareSale = 'share_sale';
    case PartialSale = 'partial_sale';
    case AssetSale = 'asset_sale';
    case PartnerEntry = 'partner_entry';
    case InvestorSearch = 'investor_search';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::FullSale => __('Full sale'),
            self::Transfer => __('Transfer'),
            self::ShareSale => __('Sale of the company'),
            self::PartialSale => __('Partial sale'),
            self::AssetSale => __('Sale of assets'),
            self::PartnerEntry => __('Partner entry'),
            self::InvestorSearch => __('Looking for investors'),
            self::Other => __('Other operation'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FullSale => __('The whole business, as it runs today.'),
            self::Transfer => __('The activity and the premises lease pass to the buyer.'),
            self::ShareSale => __('The buyer acquires the company itself, with its assets and liabilities.'),
            self::PartialSale => __('A percentage of the company is offered.'),
            self::AssetSale => __('Only equipment, stock, brand or other assets are sold.'),
            self::PartnerEntry => __('A partner joins with capital and involvement.'),
            self::InvestorSearch => __('Capital is sought without operational involvement.'),
            self::Other => __('Explain it in the conditions.'),
        };
    }

    /**
     * Wording used by the suggested title: "{operation} de {sector} en {place}".
     */
    public function titlePrefix(): string
    {
        return match ($this) {
            self::FullSale => __('Sale of'),
            self::Transfer => __('Transfer of'),
            self::ShareSale => __('Sale of the company:'),
            self::PartialSale => __('Partial sale of'),
            self::AssetSale => __('Sale of assets of'),
            self::PartnerEntry => __('Partner wanted for'),
            self::InvestorSearch => __('Investors wanted for'),
            self::Other => __('Operation on'),
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::FullSale, self::ShareSale, self::AssetSale => 'blue',
            self::Transfer => 'indigo',
            self::PartialSale, self::PartnerEntry, self::InvestorSearch => 'violet',
            self::Other => 'zinc',
        };
    }

    /**
     * Partial operations may state the percentage offered.
     */
    public function allowsStake(): bool
    {
        return in_array($this, [self::PartialSale, self::PartnerEntry, self::InvestorSearch], true);
    }
}
