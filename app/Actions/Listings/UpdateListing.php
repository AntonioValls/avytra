<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\FinancialMetric;
use App\Enums\OperationType;
use App\Models\Listing;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Updates the content of a listing (any wizard step). Never touches the status or the
 * lifecycle timestamps. Audited when the actor is not the owner.
 */
class UpdateListing
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes  fillable attributes of Listing
     * @param  list<OperationType>|null  $operationTypes  null keeps the current ones
     * @param  array<string, array{disclosure: string, amount?: int|null, amount_min?: int|null, amount_max?: int|null, period_year?: int|null}>|null  $financialMetrics  keyed by metric value; null keeps the current ones
     */
    public function handle(Listing $listing, User $actor, array $attributes, ?array $operationTypes = null, ?array $financialMetrics = null): Listing
    {
        return DB::transaction(function () use ($listing, $actor, $attributes, $operationTypes, $financialMetrics): Listing {
            $listing->fill($attributes);
            $dirty = array_keys($listing->getDirty());
            $before = Arr::only($listing->getOriginal(), $dirty);
            $after = Arr::only($listing->getAttributes(), $dirty);

            $this->stampActor($listing, $actor);
            $listing->save();

            if ($operationTypes !== null) {
                $this->syncOperationTypes($listing, $operationTypes);
            }

            if ($financialMetrics !== null) {
                $this->syncFinancialMetrics($listing, $financialMetrics);
            }

            if ($dirty !== [] || $operationTypes !== null || $financialMetrics !== null) {
                $this->auditIfOnBehalf($listing, 'listing.updated_by_admin', $actor, [
                    'before' => $this->scalars($before),
                    'after' => $this->scalars($after),
                ]);
            }

            return $listing;
        });
    }

    /**
     * @param  list<OperationType>  $types
     */
    private function syncOperationTypes(Listing $listing, array $types): void
    {
        $values = array_values(array_unique(array_map(fn (OperationType $type): string => $type->value, $types)));

        $listing->operationTypes()->whereNotIn('operation_type', $values)->delete();

        $existing = $listing->operationTypes()->pluck('operation_type')
            ->map(fn (OperationType $type): string => $type->value)
            ->all();

        foreach (array_diff($values, $existing) as $value) {
            $listing->operationTypes()->create(['operation_type' => $value]);
        }

        $listing->unsetRelation('operationTypes');
    }

    /**
     * @param  array<string, array{disclosure: string, amount?: int|null, amount_min?: int|null, amount_max?: int|null, period_year?: int|null}>  $metrics
     */
    private function syncFinancialMetrics(Listing $listing, array $metrics): void
    {
        $keep = [];

        foreach ($metrics as $metric => $values) {
            $enum = FinancialMetric::from($metric);
            $keep[] = $enum->value;

            $listing->financialMetrics()->updateOrCreate(['metric' => $enum->value], [
                'disclosure' => $values['disclosure'],
                'amount' => $values['amount'] ?? null,
                'amount_min' => $values['amount_min'] ?? null,
                'amount_max' => $values['amount_max'] ?? null,
                'period_year' => $values['period_year'] ?? null,
            ]);
        }

        $listing->financialMetrics()->whereNotIn('metric', $keep)->delete();
        $listing->unsetRelation('financialMetrics');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function scalars(array $values): array
    {
        return array_map(fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : $value, $values);
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
