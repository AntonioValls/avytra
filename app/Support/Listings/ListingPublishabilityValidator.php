<?php

namespace App\Support\Listings;

use App\Enums\PriceDisclosure;
use App\Models\Listing;

/**
 * The "ready to publish" check (docs/05-business-fields.md, summary of required fields).
 *
 * It is distinct from the per-step validation of the wizard: a draft may be as incomplete
 * as the owner wants, but publishing requires the twelve mandatory fields. Each issue
 * points to the wizard step where it is fixed so the last step can link to it.
 */
class ListingPublishabilityValidator
{
    public const int STEP_OPERATION = 1;

    public const int STEP_BASIC_INFO = 2;

    public const int STEP_ECONOMICS = 4;

    public const int STEP_LOCATION = 5;

    public const int STEP_CONTACT = 6;

    public const int STEP_PUBLISH = 8;

    public function validate(Listing $listing): PublishabilityReport
    {
        $listing->loadMissing(['business.category', 'business.location', 'business.onlineProfile', 'operationTypes']);

        $business = $listing->business;
        $issues = [];

        $offered = $listing->offeredOperationTypes();

        if ($offered === []) {
            $issues[] = new PublishabilityIssue(self::STEP_OPERATION, __('Choose at least one type of operation.'));
        } elseif ($listing->primary_operation_type === null || ! in_array($listing->primary_operation_type, $offered, true)) {
            $issues[] = new PublishabilityIssue(self::STEP_OPERATION, __('Choose which operation is the main one.'));
        }

        if (trim((string) $business->name) === '') {
            $issues[] = new PublishabilityIssue(self::STEP_BASIC_INFO, __('The business needs a trade name.'));
        }

        $minimum = (int) config('avytra.limits.description_min_length_to_publish');

        if (mb_strlen(trim((string) $business->description)) < $minimum) {
            $issues[] = new PublishabilityIssue(self::STEP_BASIC_INFO, __('Write a description of at least :min characters.', ['min' => $minimum]));
        }

        if ($business->requiresLocation()) {
            $location = $business->location;

            if ($location === null || $location->municipality_id === null) {
                $issues[] = new PublishabilityIssue(self::STEP_LOCATION, __('Indicate the province and municipality of the premises.'));
            }
        }

        if ($business->requiresOnlineProfile() && $business->onlineProfile === null) {
            $issues[] = new PublishabilityIssue(self::STEP_LOCATION, __('Indicate the type of online business.'));
        }

        $issues = [...$issues, ...$this->priceIssues($listing)];

        if ($listing->preferred_contact_method === null) {
            $issues[] = new PublishabilityIssue(self::STEP_CONTACT, __('Choose how buyers should contact you.'));
        } elseif ($listing->preferredChannelValue() === null) {
            $issues[] = new PublishabilityIssue(self::STEP_CONTACT, __('Fill in the :channel so buyers can use your preferred contact method.', [
                'channel' => mb_strtolower($listing->preferred_contact_method->label()),
            ]));
        }

        if (trim((string) $listing->title) === '') {
            $issues[] = new PublishabilityIssue(self::STEP_PUBLISH, __('Give the listing a title.'));
        } elseif (mb_strlen($listing->title) > (int) config('avytra.limits.title_max_length')) {
            $issues[] = new PublishabilityIssue(self::STEP_PUBLISH, __('The title cannot exceed :max characters.', ['max' => config('avytra.limits.title_max_length')]));
        }

        return new PublishabilityReport($issues);
    }

    /**
     * @return list<PublishabilityIssue>
     */
    private function priceIssues(Listing $listing): array
    {
        return match ($listing->price_disclosure) {
            PriceDisclosure::Exact => $listing->asking_price === null
                ? [new PublishabilityIssue(self::STEP_ECONOMICS, __('Indicate the asking price or choose “On request”.'))]
                : [],
            PriceDisclosure::Range => $listing->asking_price_min === null || $listing->asking_price_max === null || $listing->asking_price_min > $listing->asking_price_max
                ? [new PublishabilityIssue(self::STEP_ECONOMICS, __('Indicate a coherent minimum and maximum price.'))]
                : [],
            PriceDisclosure::OnRequest => [],
        };
    }
}
