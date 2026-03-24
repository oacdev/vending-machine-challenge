<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use ValueError;
use VendingMachine\Domain\Coin;

final class CoinTest extends TestCase
{
    public function test_coin_values_in_cents(): void
    {
        self::assertSame(5, Coin::Nickel->value);
        self::assertSame(10, Coin::Dime->value);
        self::assertSame(25, Coin::Quarter->value);
        self::assertSame(100, Coin::Dollar->value);
    }

    public function test_label_returns_decimal_format(): void
    {
        self::assertSame('0.05', Coin::Nickel->label());
        self::assertSame('0.10', Coin::Dime->label());
        self::assertSame('0.25', Coin::Quarter->label());
        self::assertSame('1.00', Coin::Dollar->label());
    }

    public function test_from_amount_valid(): void
    {
        self::assertSame(Coin::Nickel, Coin::fromAmount(5));
        self::assertSame(Coin::Dime, Coin::fromAmount(10));
        self::assertSame(Coin::Quarter, Coin::fromAmount(25));
        self::assertSame(Coin::Dollar, Coin::fromAmount(100));
    }

    public function test_from_amount_invalid_throws_value_error(): void
    {
        $this->expectException(ValueError::class);
        Coin::fromAmount(7);
    }

    public function test_cases_returns_all_coins(): void
    {
        $cases = Coin::cases();

        self::assertCount(4, $cases);
    }
}
