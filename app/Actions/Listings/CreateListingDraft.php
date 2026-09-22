<?php

namespace App\Actions\Listings;

use App\Actions\Listings\Concerns\RecordsListingEvents;
use App\Enums\ListingEventType;
use App\Enums\ListingStatus;
use App\Enums\OperationType;
use App\Exceptions\BusinessAlreadyListed;
use App\Models\Business;
use App\Models\Listing;
use App\Models\ListingFinancialMetric;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Creates the draft of a listing for a business, enforcing "one open listing per business".
 * Optionally copies the content of a previous listing ("publish again").
 */
class CreateListingDraft
{
    use RecordsListingEvents;

    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $attributes  fillable attributes of Listing
     * @param  list<OperationType>  $operationTypes
     */
    public function handle(Business $business, User $actor, array $attributes = [], array $operationTypes = [], ?Listing $copyFrom = null): Listing
    {
        throw_if($business->openListing()->exists(), new BusinessAlreadyListed($business));

        return DB::transaction(function () use ($business, $actor, $attributes, $operationTypes, $copyFrom): Listing {
            $listing = new Listing;

            if ($copyFrom !== null) {
                $listing->fill($copyFrom->only($listing->getFillable()));
                $listing->title = null;
            }

            $listing->fill($attributes);
            $listing->business()->associate($business);
            $listing->status = ListingStatus::Draft;
            $listing->created_by_user_id = $actor->getKey();
            $listing->updated_by_user_id = $actor->getKey();
            $listing->save();

            $types = $operationTypes !== [] ? $operationTypes : ($copyFrom?->offeredOperationTypes() ?? []);

            foreach ($types as $type) {
                $listing->operationTypes()->create(['operation_type' => $type]);
            }

            if ($listing->primary_operation_type === null && $types !== []) {
                $listing->primary_operation_type = $types[0];
                $listing->save();
            }

            $copyFrom?->financialMetrics->each(function (ListingFinancialMetric $metric) use ($listing): void {
                $listing->financialMetrics()->create($metric->only($metric->getFillable()));
            });

            $this->recordEvent($listing, ListingEventType::Created, $actor, $copyFrom === null ? [] : ['copied_from_listing_id' => $copyFrom->getKey()]);
            $this->auditIfOnBehalf($listing, 'listing.created_by_admin', $actor);

            $business->unsetRelation('openListing');
            $business->unsetRelation('listings');

            return $listing;
        });
    }

    protected function audit(): AuditLogger
    {
        return $this->audit;
    }
}
