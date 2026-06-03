<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Integration;

use PHPUnit\Framework\TestCase;
use VendingMachine\Application\Command\InsertCoinCommand;
use VendingMachine\Application\Command\InsertCoinHandler;
use VendingMachine\Application\Command\ReturnCoinsCommand;
use VendingMachine\Application\Command\ReturnCoinsHandler;
use VendingMachine\Application\Command\SelectProductCommand;
use VendingMachine\Application\Command\SelectProductHandler;
use VendingMachine\Application\Command\ServiceMachineCommand;
use VendingMachine\Application\Command\ServiceMachineHandler;
use VendingMachine\Application\Query\GetMachineStateHandler;
use VendingMachine\Application\Query\GetMachineStateQuery;
use VendingMachine\Domain\Coin;
use VendingMachine\Domain\CoinBank;
use VendingMachine\Domain\Inventory;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\VendingMachine;
use VendingMachine\Domain\VendingResultType;
use VendingMachine\Infrastructure\Persistence\InMemoryVendingMachineRepository;

final class VendingMachineFlowTest extends TestCase
{
    private InMemoryVendingMachineRepository $repository;
    private InsertCoinHandler $insertCoinHandler;
    private SelectProductHandler $selectProductHandler;
    private ReturnCoinsHandler $returnCoinsHandler;
    private ServiceMachineHandler $serviceMachineHandler;
    private GetMachineStateHandler $getMachineStateHandler;

    protected function setUp(): void
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

        $this->repository = new InMemoryVendingMachineRepository($machine);
        $this->insertCoinHandler = new InsertCoinHandler($this->repository);
        $this->selectProductHandler = new SelectProductHandler($this->repository);
        $this->returnCoinsHandler = new ReturnCoinsHandler($this->repository);
        $this->serviceMachineHandler = new ServiceMachineHandler($this->repository);
        $this->getMachineStateHandler = new GetMachineStateHandler($this->repository);
    }

    public function test_challenge_example_1_buy_soda_exact(): void
    {
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Dollar));
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Quarter));
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Quarter));

        $result = $this->selectProductHandler->handle(new SelectProductCommand(Product::Soda));

        self::assertSame(VendingResultType::Dispensed, $result->type);
        self::assertSame(Product::Soda, $result->product);
        self::assertSame([], $result->change);
    }

    public function test_challenge_example_2_return_coins(): void
    {
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Dime));
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Dime));

        $result = $this->returnCoinsHandler->handle(new ReturnCoinsCommand());

        self::assertSame(VendingResultType::CoinsReturned, $result->type);
        self::assertSame([Coin::Dime, Coin::Dime], $result->change);
    }

    public function test_challenge_example_3_buy_water_with_change(): void
    {
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Dollar));

        $result = $this->selectProductHandler->handle(new SelectProductCommand(Product::Water));

        self::assertSame(VendingResultType::Dispensed, $result->type);
        self::assertSame(Product::Water, $result->product);
        self::assertSame([Coin::Quarter, Coin::Dime], $result->change);
    }

    public function test_sequential_flow_buy_then_buy_again(): void
    {
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Dollar));
        $result1 = $this->selectProductHandler->handle(new SelectProductCommand(Product::Juice));

        self::assertSame(VendingResultType::Dispensed, $result1->type);
        self::assertSame([], $result1->change);

        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Dollar));
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Quarter));
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Quarter));
        $result2 = $this->selectProductHandler->handle(new SelectProductCommand(Product::Soda));

        self::assertSame(VendingResultType::Dispensed, $result2->type);
        self::assertSame([], $result2->change);
    }

    public function test_insufficient_funds_error(): void
    {
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Quarter));

        $result = $this->selectProductHandler->handle(new SelectProductCommand(Product::Water));

        self::assertSame(VendingResultType::Error, $result->type);
        self::assertStringContainsString('Insufficient', $result->message);
    }

    public function test_out_of_stock_error(): void
    {
        $repository = new InMemoryVendingMachineRepository(new VendingMachine(
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
        ));

        $insertCoinHandler = new InsertCoinHandler($repository);
        $selectProductHandler = new SelectProductHandler($repository);

        $insertCoinHandler->handle(new InsertCoinCommand(Coin::Dollar));
        $result = $selectProductHandler->handle(new SelectProductCommand(Product::Water));

        self::assertSame(VendingResultType::Error, $result->type);
        self::assertStringContainsString('out of stock', $result->message);
    }

    public function test_cannot_make_change_returns_coins(): void
    {
        $repository = new InMemoryVendingMachineRepository(new VendingMachine(
            CoinBank::empty(),
            Inventory::default(5),
        ));

        $insertCoinHandler = new InsertCoinHandler($repository);
        $selectProductHandler = new SelectProductHandler($repository);

        $insertCoinHandler->handle(new InsertCoinCommand(Coin::Dollar));
        $result = $selectProductHandler->handle(new SelectProductCommand(Product::Water));

        self::assertSame(VendingResultType::CoinsReturned, $result->type);
        self::assertSame([Coin::Dollar], $result->change);
    }

    public function test_service_command_resets_machine(): void
    {
        $this->insertCoinHandler->handle(new InsertCoinCommand(Coin::Dollar));
        $this->selectProductHandler->handle(new SelectProductCommand(Product::Juice));

        $state = $this->getMachineStateHandler->handle(new GetMachineStateQuery());
        self::assertSame(4, $state['inventory']->getCount(Product::Juice));

        $this->serviceMachineHandler->handle(new ServiceMachineCommand(
            CoinBank::withStock([
                Coin::Nickel->value => 10,
                Coin::Dime->value => 10,
                Coin::Quarter->value => 10,
                Coin::Dollar->value => 10,
            ]),
            Inventory::default(5),
        ));

        $state = $this->getMachineStateHandler->handle(new GetMachineStateQuery());
        self::assertSame(5, $state['inventory']->getCount(Product::Juice));
    }
}
