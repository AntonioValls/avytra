<?php

namespace App\Livewire\Forms\ListingWizard;

use App\Enums\Disclosure;
use App\Enums\FinancialMetric;
use App\Enums\PriceDisclosure;
use App\Models\Listing;
use App\Models\ListingFinancialMetric;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Step 4 of the wizard: asking price with its disclosure and the optional financial
 * metrics (ADR-010). A metric with no figure and a non "on request" disclosure is not stored.
 */
class EconomicsStepForm extends Form
{
    public string $price_disclosure = PriceDisclosure::OnRequest->value;

    public ?int $asking_price = null;

    public ?int $asking_price_min = null;

    public ?int $asking_price_max = null;

    public bool $is_price_negotiable = false;

    /**
     * Keyed by FinancialMetric value; every metric has a row so wire:model can bind to it.
     *
     * @var array<string, array{disclosure: string, amount: int|null, amount_min: int|null, amount_max: int|null}>
     */
    public array $metrics = [
        'annual_revenue' => ['disclosure' => 'exact', 'amount' => null, 'amount_min' => null, 'amount_max' => null],
        'annual_profit' => ['disclosure' => 'exact', 'amount' => null, 'amount_min' => null, 'amount_max' => null],
        'ebitda' => ['disclosure' => 'exact', 'amount' => null, 'amount_min' => null, 'amount_max' => null],
        'monthly_revenue' => ['disclosure' => 'exact', 'amount' => null, 'amount_min' => null, 'amount_max' => null],
        'monthly_recurring_revenue' => ['disclosure' => 'exact', 'amount' => null, 'amount_min' => null, 'amount_max' => null],
        'stock_value' => ['disclosure' => 'exact', 'amount' => null, 'amount_min' => null, 'amount_max' => null],
        'monthly_rent' => ['disclosure' => 'exact', 'amount' => null, 'amount_min' => null, 'amount_max' => null],
        'monthly_expenses' => ['disclosure' => 'exact', 'amount' => null, 'amount_min' => null, 'amount_max' => null],
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $exact = $this->price_disclosure === PriceDisclosure::Exact->value;
        $range = $this->price_disclosure === PriceDisclosure::Range->value;

        $rules = [
            'price_disclosure' => ['required', Rule::enum(PriceDisclosure::class)],
            'asking_price' => [Rule::requiredIf($exact), 'nullable', 'integer', 'min:0'],
            'asking_price_min' => [Rule::requiredIf($range), 'nullable', 'integer', 'min:0'],
            'asking_price_max' => [Rule::requiredIf($range), 'nullable', 'integer', 'min:0', 'gte:asking_price_min'],
            'is_price_negotiable' => ['boolean'],
            'metrics' => ['array'],
        ];

        foreach (FinancialMetric::cases() as $metric) {
            $key = "metrics.{$metric->value}";
            $isRange = ($this->metrics[$metric->value]['disclosure'] ?? '') === Disclosure::Range->value;

            $rules["{$key}.disclosure"] = ['required', Rule::enum(Disclosure::class)];
            $rules["{$key}.amount"] = ['nullable', 'integer', 'min:0'];
            $rules["{$key}.amount_min"] = ['nullable', 'integer', 'min:0'];
            $rules["{$key}.amount_max"] = ['nullable', 'integer', 'min:0', ...($isRange ? ["gte:{$key}.amount_min"] : [])];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        $attributes = [
            'price_disclosure' => __('price mode'),
            'asking_price' => __('asking price'),
            'asking_price_min' => __('minimum price'),
            'asking_price_max' => __('maximum price'),
        ];

        foreach (FinancialMetric::cases() as $metric) {
            $attributes["metrics.{$metric->value}.disclosure"] = __('disclosure');
            $attributes["metrics.{$metric->value}.amount"] = mb_strtolower($metric->label());
            $attributes["metrics.{$metric->value}.amount_min"] = __('minimum of :metric', ['metric' => mb_strtolower($metric->label())]);
            $attributes["metrics.{$metric->value}.amount_max"] = __('maximum of :metric', ['metric' => mb_strtolower($metric->label())]);
        }

        return $attributes;
    }

    public function fillFromListing(Listing $listing): void
    {
        $this->price_disclosure = $listing->price_disclosure->value;
        $this->asking_price = $listing->asking_price;
        $this->asking_price_min = $listing->asking_price_min;
        $this->asking_price_max = $listing->asking_price_max;
        $this->is_price_negotiable = $listing->is_price_negotiable === true;

        $listing->financialMetrics->each(function (ListingFinancialMetric $metric): void {
            $this->metrics[$metric->metric->value] = [
                'disclosure' => $metric->disclosure->value,
                'amount' => $metric->amount,
                'amount_min' => $metric->amount_min,
                'amount_max' => $metric->amount_max,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $disclosure = PriceDisclosure::tryFrom($this->price_disclosure) ?? PriceDisclosure::OnRequest;

        return [
            'price_disclosure' => $disclosure->value,
            'asking_price' => $disclosure === PriceDisclosure::Exact ? $this->asking_price : null,
            'asking_price_min' => $disclosure === PriceDisclosure::Range ? $this->asking_price_min : null,
            'asking_price_max' => $disclosure === PriceDisclosure::Range ? $this->asking_price_max : null,
            'is_price_negotiable' => $this->is_price_negotiable,
        ];
    }

    /**
     * Metrics actually declared, ready for UpdateListing.
     *
     * @return array<string, array{disclosure: string, amount: int|null, amount_min: int|null, amount_max: int|null}>
     */
    public function declaredMetrics(): array
    {
        $declared = [];

        foreach ($this->metrics as $key => $values) {
            $disclosure = Disclosure::tryFrom($values['disclosure']) ?? Disclosure::Exact;

            $row = match ($disclosure) {
                Disclosure::Exact, Disclosure::Hidden => ['amount' => $values['amount'] ?? null, 'amount_min' => null, 'amount_max' => null],
                Disclosure::Range => ['amount' => null, 'amount_min' => $values['amount_min'] ?? null, 'amount_max' => $values['amount_max'] ?? null],
                Disclosure::OnRequest => ['amount' => null, 'amount_min' => null, 'amount_max' => null],
            };

            $hasFigure = $row['amount'] !== null || ($row['amount_min'] !== null && $row['amount_max'] !== null);

            if ($disclosure === Disclosure::OnRequest || $hasFigure) {
                $declared[$key] = ['disclosure' => $disclosure->value, ...$row];
            }
        }

        return $declared;
    }
}
