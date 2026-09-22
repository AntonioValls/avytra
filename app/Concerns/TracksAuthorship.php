<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Fills created_by_user_id / updated_by_user_id from the authenticated user.
 *
 * Actions may set the columns explicitly (they receive the actor); the trait only
 * fills what is still empty, so commands and jobs without a user leave them null.
 * Ownership (owner_user_id) is a different concept and is never touched here.
 */
trait TracksAuthorship
{
    public static function bootTracksAuthorship(): void
    {
        static::creating(function (Model $model): void {
            $actorId = Auth::id();

            if ($model->getAttribute('created_by_user_id') === null) {
                $model->setAttribute('created_by_user_id', $actorId);
            }

            if ($model->getAttribute('updated_by_user_id') === null) {
                $model->setAttribute('updated_by_user_id', $actorId);
            }
        });

        static::updating(function (Model $model): void {
            $actorId = Auth::id();

            if ($actorId !== null && ! $model->isDirty('updated_by_user_id')) {
                $model->setAttribute('updated_by_user_id', $actorId);
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
