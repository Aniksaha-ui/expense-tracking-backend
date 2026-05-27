<?php

namespace App\Enums;

enum TransactionType: string
{
    case OPENING_BALANCE = 'OPENING_BALANCE';
    case INCOME = 'INCOME';
    case EXPENSE = 'EXPENSE';
    case WITHDRAW = 'WITHDRAW';
    case DEPOSIT = 'DEPOSIT';
    case TRANSFER = 'TRANSFER';
    case RECURRING = 'RECURRING';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
