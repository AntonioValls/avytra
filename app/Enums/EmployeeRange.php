<?php

namespace App\Enums;

enum EmployeeRange: string
{
    case None = 'none';
    case OneToTwo = 'one_to_two';
    case ThreeToFive = 'three_to_five';
    case SixToTen = 'six_to_ten';
    case ElevenToTwentyFive = 'eleven_to_twenty_five';
    case TwentySixToFifty = 'twenty_six_to_fifty';
    case MoreThanFifty = 'more_than_fifty';

    public function label(): string
    {
        return match ($this) {
            self::None => __('No employees'),
            self::OneToTwo => __('1 to 2 employees'),
            self::ThreeToFive => __('3 to 5 employees'),
            self::SixToTen => __('6 to 10 employees'),
            self::ElevenToTwentyFive => __('11 to 25 employees'),
            self::TwentySixToFifty => __('26 to 50 employees'),
            self::MoreThanFifty => __('More than 50 employees'),
        };
    }
}
