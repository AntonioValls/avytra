<?php

namespace App\Enums;

enum UserRole: string
{
    case User = 'user';
    case Superadmin = 'superadmin';

    public function label(): string
    {
        return match ($this) {
            self::User => __('User'),
            self::Superadmin => __('Superadmin'),
        };
    }
}
