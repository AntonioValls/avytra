<?php

namespace App\Enums;

/**
 * Economic figures a listing may declare, each with its own disclosure (docs/05-business-fields.md).
 */
enum FinancialMetric: string
{
    case AnnualRevenue = 'annual_revenue';
    case AnnualProfit = 'annual_profit';
    case Ebitda = 'ebitda';
    case MonthlyRevenue = 'monthly_revenue';
    case MonthlyRecurringRevenue = 'monthly_recurring_revenue';
    case StockValue = 'stock_value';
    case MonthlyRent = 'monthly_rent';
    case MonthlyExpenses = 'monthly_expenses';

    public function label(): string
    {
        return match ($this) {
            self::AnnualRevenue => __('Annual revenue'),
            self::AnnualProfit => __('Annual profit'),
            self::Ebitda => __('EBITDA'),
            self::MonthlyRevenue => __('Monthly revenue'),
            self::MonthlyRecurringRevenue => __('Monthly recurring revenue (MRR)'),
            self::StockValue => __('Stock value'),
            self::MonthlyRent => __('Monthly rent'),
            self::MonthlyExpenses => __('Monthly expenses'),
        };
    }

    public function description(): ?string
    {
        $description = match ($this) {
            self::AnnualRevenue => __('Total sales of the last financial year. The figure every buyer looks at first.'),
            self::AnnualProfit => __('Approximate profit of the last year, after expenses.'),
            self::Ebitda => __('Earnings before interest, taxes, depreciation and amortisation. Leave it empty if you do not know it.'),
            self::MonthlyRecurringRevenue => __('Subscription or recurring income per month.'),
            default => null,
        };

        return is_string($description) ? $description : null;
    }

    /**
     * Metrics shown by default in the wizard; the rest sit under "Add more figures".
     */
    public function isFeatured(): bool
    {
        return $this === self::AnnualRevenue || $this === self::AnnualProfit || $this === self::MonthlyRent;
    }

    /**
     * Some metrics only make sense for certain businesses or conditions.
     */
    public function appliesTo(BusinessType $type, ?bool $includesStock, ?bool $premisesIsRented): bool
    {
        return match ($this) {
            self::MonthlyRecurringRevenue => $type->requiresOnlineProfile(),
            self::StockValue => $includesStock === true,
            self::MonthlyRent => $premisesIsRented === true,
            default => true,
        };
    }
}
