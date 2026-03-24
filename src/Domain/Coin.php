<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

enum Coin: int
{
    case Nickel = 5;
    case Dime = 10;
    case Quarter = 25;
    case Dollar = 100;

    public function label(): string
    {
        return number_format($this->value / 100, 2);
    }

    public static function fromAmount(int $cents): self
    {
        return self::from($cents);
    }
}
