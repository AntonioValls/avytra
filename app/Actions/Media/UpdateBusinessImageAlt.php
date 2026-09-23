<?php

namespace App\Actions\Media;

use App\Models\Business;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Changes the alternative text of an image (custom property "alt", docs/17).
 * Refuses media that belongs to another model. Audited when the actor is not the owner.
 */
class UpdateBusinessImageAlt
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Business $business, User $actor, Media $media, string $alt): void
    {
        abort_unless($media->model_type === $business->getMorphClass() && (int) $media->model_id === $business->getKey(), 404);

        $before = $media->getCustomProperty('alt');
        $alt = trim($alt);

        if ($before === $alt) {
            return;
        }

        $media->setCustomProperty('alt', $alt)->save();
        $business->unsetRelation('media');

        if ($actor->isNot($business->owner)) {
            $this->audit->log(
                action: 'business.images_updated_by_admin',
                subject: $business,
                actor: $actor,
                onBehalfOf: $business->owner,
                changes: ['before' => ['alt' => $before], 'after' => ['operation' => 'alt_changed', 'media_id' => $media->getKey(), 'alt' => $alt]],
            );
        }
    }
}
