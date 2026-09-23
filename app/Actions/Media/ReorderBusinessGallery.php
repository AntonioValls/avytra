<?php

namespace App\Actions\Media;

use App\Enums\MediaCollection;
use App\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Persists the gallery order chosen by drag and drop. Ids that do not belong to the
 * gallery of the business are ignored; images left out keep their place after the rest.
 */
class ReorderBusinessGallery
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @param  list<int>  $orderedIds  media ids, first to last
     */
    public function handle(Business $business, User $actor, array $orderedIds): void
    {
        $gallery = $business->getMedia(MediaCollection::Gallery->value);
        $known = $gallery->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

        $ordered = array_values(array_filter(array_map('intval', $orderedIds), fn (int $id): bool => in_array($id, $known, true)));
        $missing = array_values(array_diff($known, $ordered));

        $ids = [...$ordered, ...$missing];

        if ($ids === []) {
            return;
        }

        Media::setNewOrder($ids);
        $business->unsetRelation('media');

        if ($actor->isNot($business->owner)) {
            $this->audit->log(
                action: 'business.images_updated_by_admin',
                subject: $business,
                actor: $actor,
                onBehalfOf: $business->owner,
                changes: ['after' => ['operation' => 'reordered', 'collection' => MediaCollection::Gallery->value, 'order' => $ids]],
            );
        }
    }
}
