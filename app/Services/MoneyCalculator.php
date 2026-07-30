<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class MoneyCalculator
{
    public function normalize(BigDecimal|float|int|string $value): BigDecimal
    {
        return BigDecimal::of((string) $value)->toScale(2, RoundingMode::HalfUp);
    }

    public function add(BigDecimal|float|int|string ...$values): BigDecimal
    {
        $total = $this->normalize(0);

        foreach ($values as $value) {
            $total = $total->plus($this->normalize($value));
        }

        return $total->toScale(2, RoundingMode::HalfUp);
    }

    public function subtract(BigDecimal|float|int|string $left, BigDecimal|float|int|string $right): BigDecimal
    {
        return $this->normalize($left)
            ->minus($this->normalize($right))
            ->toScale(2, RoundingMode::HalfUp);
    }

    public function multiply(BigDecimal|float|int|string $value, int $quantity): BigDecimal
    {
        return $this->normalize($value)
            ->multipliedBy($quantity)
            ->toScale(2, RoundingMode::HalfUp);
    }

    public function toFloat(BigDecimal $value): float
    {
        return $value->toScale(2, RoundingMode::HalfUp)->toFloat();
    }
}
