<?php

namespace App\Actions\Contact;

use App\Models\ContactRequest;

/**
 * Marks a relayed message as read the first time the owner opens it in the panel.
 */
class MarkContactRequestAsRead
{
    public function handle(ContactRequest $request): ContactRequest
    {
        if ($request->isRead()) {
            return $request;
        }

        $request->forceFill(['read_at' => now()])->save();

        return $request;
    }
}
