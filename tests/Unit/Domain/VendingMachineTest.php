<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\CoinBank;
use VendingMachine\Domain\Exception\CannotMakeChangeException;
use VendingMachine\Domain\Exception\InsufficientFundsException;
use VendingMachine\Domain\Exception\OutOfStockException;
use VendingMachine\Domain\Inventory;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\VendingMachine;
use VendingMachine\Domain\VendingResultType;

final class VendingMachineTest extends TestCase
{
    private function createMachine(
        ?CoinBank $coinBank = null,
        ?Inventory $inventory = null,
    ): VendingMachine {
        return new VendingMachine(
            $coinBank ?? CoinBank::withStock([
                Coin::Nickel->value => 10,
                Coin::Dime->value => 10,
                Coin::Quarter->value => 10,
                Coin::Dollar->value => 10,
            ]),
            $inventory ?? Inventory::default(5),
        );
    }

    public function test_buy_with_exact_change(): void
    {
        $machine = $this->createMachine();

        $machine->insertCoin(Coin::Quarter);
        $machine->insertCoin(Coin::Quarter);
        $machine->insertCoin(Coin::Dime);
        $machine->insertCoin(Coin::Nickel);

        $result = $machine->selectProduct(Product::Water);

        self::assertSame(VendingResultType::Dispensed, $result->type);
        self::assertSame(Product::Water, $result->product);
        self::assertSame([], $result->change);
    }

    public function test_buy_with_overpayment_returns_change(): void
    {
        $machine = $this->createMachine();

        $machine->insertCoin(Coin::Dollar);

        $result = $machine->selectProduct(Product::Water);

        self::assertSame(VendingResultType::Dispensed, $result->type);
        self::assertSame(Product::Water, $result->product);

        $changeTotal = array_sum(array_map(fn (Coin $c) => $c->value, $result->change));
        self::assertSame(35, $changeTotal);
    }

    public function test_insufficient_funds_throws_exception(): void
    {
        $machine = $this->createMachine();

        $machine->insertCoin(Coin::Quarter);

        $this->expectException(InsufficientFundsException::class);
        $machine->selectProduct(Product::Water);
    }

    public function test_out_of_stock_throws_exception(): void
    {
        $machine = $this->createMachine(
            inventory: Inventory::withStock([
                Product::Water->value => 0,
                Product::Juice->value => 5,
                Product::Soda->value => 5,
            ]),
        );

        $machine->insertCoin(Coin::Dollar);

        $this->expectException(OutOfStockException::class);
        $machine->selectProduct(Product::Water);
    }

    public function test_cannot_make_change_throws_exception(): void
    {
        $machine = $this->createMachine(
            coinBank: CoinBank::empty(),
        );

        $machine->insertCoin(Coin::Dollar);

        $this->expectException(CannotMakeChangeException::class);
        $machine->selectProduct(Product::Water);
    }

    public function test_cannot_make_change_does_not_alter_state(): void
    {
        $machine = $this->createMachine(
            coinBank: CoinBank::empty(),
        );

        $machine->insertCoin(Coin::Dollar);

        try {
            $machine->selectProduct(Product::Water);
        } catch (CannotMakeChangeException) {
            // State should be unchanged — coins still inserted
            self::assertSame(100, $machine->getInsertedAmount());

            // Bank should still be empty (rollback)
            $state = $machine->getState();
            self::assertTrue($state['coinBank']->isEmpty());

            return;
        }

        self::fail('Expected CannotMakeChangeException');
    }

    public function test_return_coins_with_coins_inserted(): void
    {
        $machine = $this->createMachine();

        $machine->insertCoin(Coin::Dime);
        $machine->insertCoin(Coin::Quarter);

        $returned = $machine->returnCoins();

        self::assertSame([Coin::Dime, Coin::Quarter], $returned);
        self::assertSame(0, $machine->getInsertedAmount());
    }

    public function test_return_coins_without_coins_inserted(): void
    {
        $machine = $this->createMachine();

        $returned = $machine->returnCoins();

        self::assertSame([], $returned);
    }

    public function test_multiple_coins_accumulate(): void
    {
        $machine = $this->createMachine();

        $machine->insertCoin(Coin::Nickel);
        self::assertSame(5, $machine->getInsertedAmount());

        $machine->insertCoin(Coin::Dime);
        self::assertSame(15, $machine->getInsertedAmount());

        $machine->insertCoin(Coin::Quarter);
        self::assertSame(40, $machine->getInsertedAmount());

        $machine->insertCoin(Coin::Dollar);
        self::assertSame(140, $machine->getInsertedAmount());
    }

    public function test_service_resets_state(): void
    {
        $machine = $this->createMachine();

        $machine->insertCoin(Coin::Dollar);

        $newBank = CoinBank::withStock([Coin::Nickel->value => 20]);
        $newInventory = Inventory::default(10);

        $machine->service($newBank, $newInventory);

        self::assertSame(0, $machine->getInsertedAmount());

        $state = $machine->getState();
        self::assertSame(20, $state['coinBank']->getCount(Coin::Nickel));
        self::assertSame(10, $state['inventory']->getCount(Product::Water));
    }

    public function test_inserted_coins_added_to_bank_after_purchase(): void
    {
        $machine = $this->createMachine(
            coinBank: CoinBank::empty(),
        );

        // Insert exact change — coins go into bank
        $machine->insertCoin(Coin::Quarter);
        $machine->insertCoin(Coin::Quarter);
        $machine->insertCoin(Coin::Dime);
        $machine->insertCoin(Coin::Nickel);

        $machine->selectProduct(Product::Water);

        $state = $machine->getState();
        self::assertSame(2, $state['coinBank']->getCount(Coin::Quarter));
        self::assertSame(1, $state['coinBank']->getCount(Coin::Dime));
        self::assertSame(1, $state['coinBank']->getCount(Coin::Nickel));
    }

    public function test_sequential_purchases_maintain_state(): void
    {
        $machine = $this->createMachine(
            coinBank: CoinBank::withStock([
                Coin::Nickel->value => 5,
                Coin::Dime->value => 5,
                Coin::Quarter->value => 5,
            ]),
        );

        // First purchase: exact change for Water (65)
        $machine->insertCoin(Coin::Quarter);
        $machine->insertCoin(Coin::Quarter);
        $machine->insertCoin(Coin::Dime);
        $machine->insertCoin(Coin::Nickel);
        $machine->selectProduct(Product::Water);

        $state = $machine->getState();
        self::assertSame(4, $state['inventory']->getCount(Product::Water));

        // Second purchase: exact change for Juice (100)
        $machine->insertCoin(Coin::Dollar);
        $machine->selectProduct(Product::Juice);

        $state = $machine->getState();
        self::assertSame(4, $state['inventory']->getCount(Product::Juice));
    }

    // ---- The 3 challenge examples ----

    public function test_challenge_example_1_buy_soda_exact_change(): void
    {
        $machine = $this->createMachine();

        // 1.00 + 0.25 + 0.25 = 1.50 → GET-SODA (1.50) → no change
        $machine->insertCoin(Coin::Dollar);
        $machine->insertCoin(Coin::Quarter);
        $machine->insertCoin(Coin::Quarter);

        $result = $machine->selectProduct(Product::Soda);

        self::assertSame(VendingResultType::Dispensed, $result->type);
        self::assertSame(Product::Soda, $result->product);
        self::assertSame([], $result->change);
    }

    public function test_challenge_example_2_return_coins(): void
    {
        $machine = $this->createMachine();

        // 0.10 + 0.10, RETURN-COIN → [0.10, 0.10]
        $machine->insertCoin(Coin::Dime);
        $machine->insertCoin(Coin::Dime);

        $returned = $machine->returnCoins();

        self::assertSame([Coin::Dime, Coin::Dime], $returned);
    }

    public function test_challenge_example_3_buy_water_with_change(): void
    {
        $machine = $this->createMachine();

        // 1.00, GET-WATER (0.65) → WATER, change = 0.35
        // Greedy: 0.25 + 0.10 = 0.35
        $machine->insertCoin(Coin::Dollar);

        $result = $machine->selectProduct(Product::Water);

        self::assertSame(VendingResultType::Dispensed, $result->type);
        self::assertSame(Product::Water, $result->product);
        self::assertSame([Coin::Quarter, Coin::Dime], $result->change);
    }
}
