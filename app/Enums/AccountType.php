<?php

namespace App\Enums;

enum AccountType: string
{
    case CARD = 'CARD';
    case BANK = 'BANK';
    case CASH = 'CASH';
    case MOBILE_BANKING = 'MOBILE_BANKING';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
