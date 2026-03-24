<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

enum Product: string
{
    case Water = 'water';
    case Juice = 'juice';
    case Soda = 'soda';

    public function priceInCents(): int
    {
        return match ($this) {
            self::Water => 65,
            self::Juice => 100,
            self::Soda => 150,
        };
    }

    public function label(): string
    {
        return strtoupper($this->value);
    }
}
