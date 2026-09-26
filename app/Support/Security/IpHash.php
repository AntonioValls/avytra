<?php

namespace App\Support\Security;

/**
 * Stores visitor IPs only as a keyed hash: enough to spot repeats and abuse in public
 * forms (reports, contact requests), never the address itself (docs/16).
 */
class IpHash
{
    public static function make(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.'|'.config('app.key'));
    }
}
