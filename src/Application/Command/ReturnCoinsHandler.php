<?php

declare(strict_types=1);

namespace VendingMachine\Application\Command;

use VendingMachine\Domain\VendingMachineRepositoryInterface;
use VendingMachine\Domain\VendingResult;

final class ReturnCoinsHandler
{
    public function __construct(private readonly VendingMachineRepositoryInterface $repository)
    {
    }

    public function handle(ReturnCoinsCommand $command): VendingResult
    {
        $machine = $this->repository->get();
        $coins = $machine->returnCoins();
        $this->repository->save($machine);

        return VendingResult::coinsReturned($coins);
    }
}
