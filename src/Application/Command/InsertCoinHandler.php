<?php

declare(strict_types=1);

namespace VendingMachine\Application\Command;

use VendingMachine\Domain\VendingMachineRepositoryInterface;
use VendingMachine\Domain\VendingResult;

final class InsertCoinHandler
{
    public function __construct(private readonly VendingMachineRepositoryInterface $repository)
    {
    }

    public function handle(InsertCoinCommand $command): VendingResult
    {
        $machine = $this->repository->get();
        $machine->insertCoin($command->coin);
        $this->repository->save($machine);

        return VendingResult::error(sprintf(
            'Inserted %s. Current balance: %s.',
            $command->coin->label(),
            number_format($machine->getInsertedAmount() / 100, 2),
        ));
    }
}
