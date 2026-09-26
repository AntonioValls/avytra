<?php

namespace App\Actions\Contact;

use App\Models\ContactRequest;
use App\Models\Listing;
use App\Models\User;
use App\Notifications\ContactRequestReceived;
use App\Support\Security\IpHash;
use Illuminate\Support\Facades\Notification;

/**
 * Stores a message from the public listing page and relays it by email to the seller
 * (docs/12, ADR-019). The recipient is the listing contact email or, failing that, the
 * account email of the owner; the sender is never emailed, so the relay cannot be used to
 * send mail to third parties.
 */
class SubmitContactRequest
{
    /**
     * @param  array{name: string, email: string, phone?: string|null, message: string}  $data
     */
    public function handle(Listing $listing, ?User $sender, ?string $ip, array $data): ContactRequest
    {
        $request = $listing->contactRequests()->create([
            'sender_user_id' => $sender?->getKey(),
            'sender_name' => trim($data['name']),
            'sender_email' => trim($data['email']),
            'sender_phone' => filled($data['phone'] ?? null) ? trim((string) $data['phone']) : null,
            'message' => trim($data['message']),
            'ip_hash' => self::hashIp($ip),
        ]);

        Notification::route('mail', $listing->contactInboxEmail())->notify(new ContactRequestReceived($request));

        return $request;
    }

    /**
     * Messages the same visitor already sent to this listing today.
     */
    public static function sentToday(Listing $listing, ?string $ip): int
    {
        $hash = self::hashIp($ip);

        if ($hash === null) {
            return 0;
        }

        return $listing->contactRequests()
            ->where('ip_hash', $hash)
            ->where('created_at', '>=', now()->subDay())
            ->count();
    }

    public static function hashIp(?string $ip): ?string
    {
        return IpHash::make($ip);
    }
}
