<?php

declare(strict_types=1);

namespace VendingMachine\Application\Command;

use VendingMachine\Domain\Exception\CannotMakeChangeException;
use VendingMachine\Domain\Exception\InsufficientFundsException;
use VendingMachine\Domain\Exception\OutOfStockException;
use VendingMachine\Domain\VendingMachineRepositoryInterface;
use VendingMachine\Domain\VendingResult;

final class SelectProductHandler
{
    public function __construct(private readonly VendingMachineRepositoryInterface $repository)
    {
    }

    public function handle(SelectProductCommand $command): VendingResult
    {
        $machine = $this->repository->get();

        try {
            $result = $machine->selectProduct($command->product);
            $this->repository->save($machine);

            return $result;
        } catch (OutOfStockException $e) {
            return VendingResult::error($e->getMessage());
        } catch (InsufficientFundsException $e) {
            return VendingResult::error($e->getMessage());
        } catch (CannotMakeChangeException) {
            $coins = $machine->returnCoins();
            $this->repository->save($machine);

            return VendingResult::coinsReturned($coins);
        }
    }
}
