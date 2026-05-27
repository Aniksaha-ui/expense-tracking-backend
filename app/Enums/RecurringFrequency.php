<?php

namespace App\Enums;

enum RecurringFrequency: string
{
    case DAILY = 'DAILY';
    case WEEKLY = 'WEEKLY';
    case MONTHLY = 'MONTHLY';
    case YEARLY = 'YEARLY';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
