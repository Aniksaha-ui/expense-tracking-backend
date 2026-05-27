<?php

namespace App\Support;

use Brick\Math\BigDecimal;

class MoneyHelper
{
    public static function add(string $left, string $right): string
    {
        return (string) BigDecimal::of($left)->plus($right)->toScale(2);
    }

    public static function subtract(string $left, string $right): string
    {
        return (string) BigDecimal::of($left)->minus($right)->toScale(2);
    }

    public static function greaterThan(string $left, string $right): bool
    {
        return BigDecimal::of($left)->isGreaterThan($right);
    }

    public static function lessThan(string $left, string $right): bool
    {
        return BigDecimal::of($left)->isLessThan($right);
    }
}
