<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\CoinBank;

final class CoinBankTest extends TestCase
{
    public function test_empty_bank_has_zero_total(): void
    {
        $bank = CoinBank::empty();

        self::assertSame(0, $bank->totalInCents());
        self::assertTrue($bank->isEmpty());
    }

    public function test_add_coin_returns_new_instance(): void
    {
        $bank = CoinBank::empty();
        $newBank = $bank->addCoin(Coin::Quarter);

        self::assertSame(0, $bank->totalInCents());
        self::assertSame(25, $newBank->totalInCents());
    }

    public function test_add_coins_returns_new_instance(): void
    {
        $bank = CoinBank::empty();
        $newBank = $bank->addCoins([Coin::Quarter, Coin::Dime]);

        self::assertSame(0, $bank->totalInCents());
        self::assertSame(35, $newBank->totalInCents());
    }

    public function test_remove_coin_returns_new_instance(): void
    {
        $bank = CoinBank::empty()->addCoin(Coin::Quarter)->addCoin(Coin::Quarter);
        $newBank = $bank->removeCoin(Coin::Quarter);

        self::assertSame(50, $bank->totalInCents());
        self::assertSame(25, $newBank->totalInCents());
    }

    public function test_remove_coins_returns_new_instance(): void
    {
        $bank = CoinBank::empty()->addCoins([Coin::Quarter, Coin::Dime, Coin::Nickel]);
        $newBank = $bank->removeCoins([Coin::Quarter, Coin::Nickel]);

        self::assertSame(40, $bank->totalInCents());
        self::assertSame(10, $newBank->totalInCents());
    }

    public function test_get_count(): void
    {
        $bank = CoinBank::empty()
            ->addCoin(Coin::Quarter)
            ->addCoin(Coin::Quarter)
            ->addCoin(Coin::Dime);

        self::assertSame(2, $bank->getCount(Coin::Quarter));
        self::assertSame(1, $bank->getCount(Coin::Dime));
        self::assertSame(0, $bank->getCount(Coin::Dollar));
    }

    public function test_with_stock(): void
    {
        $bank = CoinBank::withStock([
            Coin::Dollar->value => 2,
            Coin::Quarter->value => 5,
        ]);

        self::assertSame(2, $bank->getCount(Coin::Dollar));
        self::assertSame(5, $bank->getCount(Coin::Quarter));
        self::assertSame(0, $bank->getCount(Coin::Dime));
        self::assertSame(325, $bank->totalInCents());
    }

    public function test_total_calculation(): void
    {
        $bank = CoinBank::withStock([
            Coin::Dollar->value => 1,
            Coin::Quarter->value => 2,
            Coin::Dime->value => 3,
            Coin::Nickel->value => 4,
        ]);

        // 100 + 50 + 30 + 20 = 200
        self::assertSame(200, $bank->totalInCents());
    }

    public function test_make_change_zero_returns_empty_array(): void
    {
        $bank = CoinBank::empty();
        $change = $bank->makeChange(0);

        self::assertSame([], $change);
    }

    public function test_make_change_exact_single_coin(): void
    {
        $bank = CoinBank::empty()->addCoin(Coin::Quarter);
        $change = $bank->makeChange(25);

        self::assertNotNull($change);
        self::assertCount(1, $change);
        self::assertSame(Coin::Quarter, $change[0]);
    }

    public function test_make_change_multiple_denominations(): void
    {
        $bank = CoinBank::withStock([
            Coin::Quarter->value => 5,
            Coin::Dime->value => 5,
            Coin::Nickel->value => 5,
        ]);

        $change = $bank->makeChange(35);

        self::assertNotNull($change);
        // Greedy: 25 + 10 = 35
        self::assertSame([Coin::Quarter, Coin::Dime], $change);
    }

    public function test_make_change_greedy_order_largest_first(): void
    {
        $bank = CoinBank::withStock([
            Coin::Dollar->value => 1,
            Coin::Quarter->value => 5,
            Coin::Dime->value => 5,
            Coin::Nickel->value => 5,
        ]);

        $change = $bank->makeChange(140);

        self::assertNotNull($change);
        // Greedy: 100 + 25 + 10 + 5 = 140
        self::assertSame([Coin::Dollar, Coin::Quarter, Coin::Dime, Coin::Nickel], $change);
    }

    public function test_make_change_impossible_returns_null(): void
    {
        $bank = CoinBank::withStock([
            Coin::Quarter->value => 1,
        ]);

        $change = $bank->makeChange(10);

        self::assertNull($change);
    }

    public function test_make_change_empty_bank_returns_null(): void
    {
        $bank = CoinBank::empty();
        $change = $bank->makeChange(5);

        self::assertNull($change);
    }

    public function test_make_change_uses_multiple_of_same_coin(): void
    {
        $bank = CoinBank::withStock([
            Coin::Nickel->value => 3,
        ]);

        $change = $bank->makeChange(15);

        self::assertNotNull($change);
        self::assertSame([Coin::Nickel, Coin::Nickel, Coin::Nickel], $change);
    }

    public function test_make_change_insufficient_quantity(): void
    {
        $bank = CoinBank::withStock([
            Coin::Quarter->value => 1,
            Coin::Dime->value => 0,
            Coin::Nickel->value => 0,
        ]);

        // Need 50 cents but only have one quarter
        $change = $bank->makeChange(50);

        self::assertNull($change);
    }

    public function test_immutability_original_unchanged_after_add(): void
    {
        $original = CoinBank::empty();
        $original->addCoin(Coin::Dollar);

        self::assertSame(0, $original->totalInCents());
        self::assertTrue($original->isEmpty());
    }

    public function test_to_array(): void
    {
        $bank = CoinBank::withStock([
            Coin::Dollar->value => 1,
            Coin::Quarter->value => 2,
            Coin::Dime->value => 3,
            Coin::Nickel->value => 4,
        ]);

        $array = $bank->toArray();

        self::assertSame(1, $array[Coin::Dollar->value]);
        self::assertSame(2, $array[Coin::Quarter->value]);
        self::assertSame(3, $array[Coin::Dime->value]);
        self::assertSame(4, $array[Coin::Nickel->value]);
    }

    public function test_is_empty_false_when_has_coins(): void
    {
        $bank = CoinBank::empty()->addCoin(Coin::Nickel);

        self::assertFalse($bank->isEmpty());
    }
}
