<?php

namespace App\Actions\Media;

use App\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Deletes an image of the business together with its files and conversions on every disk.
 * Refuses media that belongs to another model. Audited when the actor is not the owner.
 */
class RemoveBusinessImage
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Business $business, User $actor, Media $media): void
    {
        $this->assertBelongsTo($business, $media);

        $collection = $media->collection_name;
        $mediaId = $media->getKey();

        $media->delete();
        $business->unsetRelation('media');

        if ($actor->isNot($business->owner)) {
            $this->audit->log(
                action: 'business.images_updated_by_admin',
                subject: $business,
                actor: $actor,
                onBehalfOf: $business->owner,
                changes: ['after' => ['operation' => 'removed', 'collection' => $collection, 'media_id' => $mediaId]],
            );
        }
    }

    private function assertBelongsTo(Business $business, Media $media): void
    {
        abort_unless($media->model_type === $business->getMorphClass() && (int) $media->model_id === $business->getKey(), 404);
    }
}
