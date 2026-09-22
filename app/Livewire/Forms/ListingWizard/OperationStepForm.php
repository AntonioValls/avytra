<?php

namespace App\Livewire\Forms\ListingWizard;

use App\Enums\OperationType;
use App\Models\Listing;
use Illuminate\Validation\Rule;
use Livewire\Form;

/**
 * Step 1 of the wizard: what is offered (one or several operation types, ADR-015),
 * which one is the main one, and the stake/conditions for partial operations.
 */
class OperationStepForm extends Form
{
    /** @var list<string> */
    public array $operation_types = [];

    public ?string $primary_operation_type = null;

    public ?int $stake_percent = null;

    public string $operation_notes = '';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $this->normalise();

        return [
            'operation_types' => ['required', 'array', 'min:1'],
            'operation_types.*' => [Rule::enum(OperationType::class)],
            'primary_operation_type' => ['required', Rule::enum(OperationType::class), Rule::in($this->operation_types)],
            'stake_percent' => ['nullable', 'integer', 'between:1,100'],
            'operation_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'operation_types.required' => __('Choose at least one type of operation.'),
            'primary_operation_type.required' => __('Choose which operation is the main one.'),
            'primary_operation_type.in' => __('The main operation must be one of the operations you offer.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function validationAttributes(): array
    {
        return [
            'operation_types' => __('operation types'),
            'primary_operation_type' => __('main operation'),
            'stake_percent' => __('percentage offered'),
            'operation_notes' => __('conditions of the operation'),
        ];
    }

    /**
     * With a single operation there is nothing to choose: it is the main one.
     * The stake only makes sense for partial operations.
     */
    public function normalise(): void
    {
        $this->operation_types = array_values(array_unique(array_filter($this->operation_types, fn (string $value): bool => $value !== '')));

        if (count($this->operation_types) === 1) {
            $this->primary_operation_type = $this->operation_types[0];
        } elseif ($this->primary_operation_type !== null && ! in_array($this->primary_operation_type, $this->operation_types, true)) {
            $this->primary_operation_type = null;
        }

        if (! $this->primary()?->allowsStake()) {
            $this->stake_percent = null;
        }
    }

    public function primary(): ?OperationType
    {
        return $this->primary_operation_type === null ? null : OperationType::tryFrom($this->primary_operation_type);
    }

    public function offersPartialOperation(): bool
    {
        foreach ($this->operationTypes() as $type) {
            if ($type->allowsStake()) {
                return true;
            }
        }

        return false;
    }

    public function fillFromListing(Listing $listing): void
    {
        $this->operation_types = array_map(fn (OperationType $type): string => $type->value, $listing->offeredOperationTypes());
        $this->primary_operation_type = $listing->primary_operation_type?->value;
        $this->stake_percent = $listing->stake_percent;
        $this->operation_notes = $listing->operation_notes ?? '';
    }

    /**
     * @return list<OperationType>
     */
    public function operationTypes(): array
    {
        return array_values(array_filter(array_map(fn (string $value): ?OperationType => OperationType::tryFrom($value), $this->operation_types)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $this->normalise();

        $notes = trim($this->operation_notes);

        return [
            'primary_operation_type' => $this->primary_operation_type,
            'stake_percent' => $this->stake_percent,
            'operation_notes' => $notes === '' ? null : $notes,
        ];
    }
}
