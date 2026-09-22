<?php

namespace App\Enums;

enum LogisticsType: string
{
    case Own = 'own';
    case Outsourced = 'outsourced';
    case Dropshipping = 'dropshipping';
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Own => __('Own logistics'),
            self::Outsourced => __('Outsourced logistics (3PL)'),
            self::Dropshipping => __('Dropshipping'),
            self::None => __('No logistics'),
        };
    }
}
