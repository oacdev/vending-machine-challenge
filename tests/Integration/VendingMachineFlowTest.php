<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use VendingMachine\Application\VendingMachineService;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\CoinBank;
use VendingMachine\Domain\Inventory;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\VendingMachine;
use VendingMachine\Domain\VendingResultType;

final class VendingMachineFlowTest extends TestCase
{
    private function createService(): VendingMachineService
    {
        $machine = new VendingMachine(
            CoinBank::withStock([
                Coin::Nickel->value => 10,
                Coin::Dime->value => 10,
                Coin::Quarter->value => 10,
                Coin::Dollar->value => 10,
            ]),
            Inventory::default(5),
        );

        return new VendingMachineService($machine);
    }

    public function test_challenge_example_1_buy_soda_exact(): void
    {
        $service = $this->createService();

        $service->handleInput('1.00');
        $service->handleInput('0.25');
        $service->handleInput('0.25');

        $result = $service->handleInput('GET-SODA');

        self::assertSame(VendingResultType::Dispensed, $result->type);
        self::assertSame(Product::Soda, $result->product);
        self::assertSame([], $result->change);
    }

    public function test_challenge_example_2_return_coins(): void
    {
        $service = $this->createService();

        $service->handleInput('0.10');
        $service->handleInput('0.10');

        $result = $service->handleInput('RETURN-COIN');

        self::assertSame(VendingResultType::CoinsReturned, $result->type);
        self::assertSame([Coin::Dime, Coin::Dime], $result->change);
    }

    public function test_challenge_example_3_buy_water_with_change(): void
    {
        $service = $this->createService();

        $service->handleInput('1.00');

        $result = $service->handleInput('GET-WATER');

        self::assertSame(VendingResultType::Dispensed, $result->type);
        self::assertSame(Product::Water, $result->product);
        self::assertSame([Coin::Quarter, Coin::Dime], $result->change);
    }

    public function test_sequential_flow_buy_then_buy_again(): void
    {
        $service = $this->createService();

        // First purchase
        $service->handleInput('1.00');
        $result1 = $service->handleInput('GET-JUICE');

        self::assertSame(VendingResultType::Dispensed, $result1->type);
        self::assertSame([], $result1->change);

        // Second purchase
        $service->handleInput('1.00');
        $service->handleInput('0.25');
        $service->handleInput('0.25');
        $result2 = $service->handleInput('GET-SODA');

        self::assertSame(VendingResultType::Dispensed, $result2->type);
        self::assertSame([], $result2->change);
    }

    public function test_insufficient_funds_error(): void
    {
        $service = $this->createService();

        $service->handleInput('0.25');

        $result = $service->handleInput('GET-WATER');

        self::assertSame(VendingResultType::Error, $result->type);
        self::assertStringContainsString('Insufficient', $result->message);
    }

    public function test_out_of_stock_error(): void
    {
        $service = new VendingMachineService(
            new VendingMachine(
                CoinBank::withStock([
                    Coin::Nickel->value => 10,
                    Coin::Dime->value => 10,
                    Coin::Quarter->value => 10,
                    Coin::Dollar->value => 10,
                ]),
                Inventory::withStock([
                    Product::Water->value => 0,
                    Product::Juice->value => 5,
                    Product::Soda->value => 5,
                ]),
            ),
        );

        $service->handleInput('1.00');

        $result = $service->handleInput('GET-WATER');

        self::assertSame(VendingResultType::Error, $result->type);
        self::assertStringContainsString('out of stock', $result->message);
    }

    public function test_cannot_make_change_returns_coins(): void
    {
        $service = new VendingMachineService(
            new VendingMachine(
                CoinBank::empty(),
                Inventory::default(5),
            ),
        );

        $service->handleInput('1.00');

        $result = $service->handleInput('GET-WATER');

        self::assertSame(VendingResultType::CoinsReturned, $result->type);
        self::assertSame([Coin::Dollar], $result->change);
    }

    public function test_unknown_product_error(): void
    {
        $service = $this->createService();

        $result = $service->handleInput('GET-PIZZA');

        self::assertSame(VendingResultType::Error, $result->type);
        self::assertStringContainsString('Unknown product', $result->message);
    }

    public function test_unknown_command_error(): void
    {
        $service = $this->createService();

        $result = $service->handleInput('HELLO');

        self::assertSame(VendingResultType::Error, $result->type);
        self::assertStringContainsString('Unknown command', $result->message);
    }

    public function test_service_command_resets_machine(): void
    {
        $service = $this->createService();

        // Deplete some stock
        $service->handleInput('1.00');
        $service->handleInput('GET-JUICE');

        $state = $service->getMachine()->getState();
        self::assertSame(4, $state['inventory']->getCount(Product::Juice));

        // Service resets
        $service->handleInput('SERVICE');

        $state = $service->getMachine()->getState();
        self::assertSame(5, $state['inventory']->getCount(Product::Juice));
    }

    public function test_case_insensitive_input(): void
    {
        $service = $this->createService();

        $service->handleInput('1.00');

        $result = $service->handleInput('get-juice');

        self::assertSame(VendingResultType::Dispensed, $result->type);
        self::assertSame(Product::Juice, $result->product);
    }
}
