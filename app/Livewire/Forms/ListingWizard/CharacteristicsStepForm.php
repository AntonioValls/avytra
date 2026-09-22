<?php

namespace App\Livewire\Forms\ListingWizard;

use App\Models\Listing;
use Livewire\Form;

/**
 * Step 3 of the wizard: reason for sale, highlights and what the operation includes.
 * The "includes" booleans are tri-state: '' (not indicated), 'yes' or 'no'.
 */
class CharacteristicsStepForm extends Form
{
    public string $reason_for_sale = '';

    /** @var list<string> */
    public array $highlights = [''];

    public string $includes_stock = '';

    public string $includes_equipment = '';

    public string $includes_property = '';

    public string $includes_staff = '';

    public string $includes_intellectual_property = '';

    public string $included_assets_notes = '';

    public string $premises_is_rented = '';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $triState = ['nullable', 'in:,yes,no'];

        return [
            'reason_for_sale' => ['nullable', 'string', 'max:500'],
            'highlights' => ['array', 'max:'.config('avytra.limits.highlights_max')],
            'highlights.*' => ['nullable', 'string', 'max:'.config('avytra.limits.highlight_max_length')],
            'includes_stock' => $triState,
            'includes_equipment' => $triState,
            'includes_property' => $triState,
            'includes_staff' => $triState,
            'includes_intellectual_property' => $triState,
            'included_assets_notes' => ['nullable', 'string', 'max:2000'],
            'premises_is_rented' => $triState,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'reason_for_sale' => __('reason for sale'),
            'highlights' => __('highlights'),
            'highlights.*' => __('highlight'),
            'included_assets_notes' => __('included assets'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function includeFields(): array
    {
        return ['includes_stock', 'includes_equipment', 'includes_property', 'includes_staff', 'includes_intellectual_property'];
    }

    public function addHighlight(): void
    {
        if (count($this->highlights) < (int) config('avytra.limits.highlights_max')) {
            $this->highlights[] = '';
        }
    }

    public function removeHighlight(int $index): void
    {
        array_splice($this->highlights, $index, 1);

        if ($this->highlights === []) {
            $this->highlights = [''];
        }
    }

    public function fillFromListing(Listing $listing): void
    {
        $this->reason_for_sale = $listing->reason_for_sale ?? '';
        $this->highlights = $listing->highlights === null || $listing->highlights === [] ? [''] : $listing->highlights;

        foreach (self::includeFields() as $field) {
            $this->{$field} = self::fromBool($listing->{$field});
        }

        $this->included_assets_notes = $listing->included_assets_notes ?? '';
        $this->premises_is_rented = self::fromBool($listing->premises_is_rented);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $highlights = array_values(array_filter(array_map(fn (string $value): string => trim($value), $this->highlights), fn (string $value): bool => $value !== ''));

        $attributes = [
            'reason_for_sale' => $this->nullable($this->reason_for_sale),
            'highlights' => $highlights === [] ? null : $highlights,
            'included_assets_notes' => $this->nullable($this->included_assets_notes),
            'premises_is_rented' => self::toBool($this->premises_is_rented),
        ];

        foreach (self::includeFields() as $field) {
            $attributes[$field] = self::toBool($this->{$field});
        }

        return $attributes;
    }

    public function premisesIsRented(): bool
    {
        return $this->premises_is_rented === 'yes';
    }

    public function includesStock(): bool
    {
        return $this->includes_stock === 'yes';
    }

    private static function fromBool(?bool $value): string
    {
        return match ($value) {
            true => 'yes',
            false => 'no',
            null => '',
        };
    }

    private static function toBool(string $value): ?bool
    {
        return match ($value) {
            'yes' => true,
            'no' => false,
            default => null,
        };
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
