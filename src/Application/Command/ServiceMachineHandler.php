<?php

declare(strict_types=1);

namespace VendingMachine\Application\Command;

use VendingMachine\Domain\VendingMachineRepositoryInterface;
use VendingMachine\Domain\VendingResult;

final class ServiceMachineHandler
{
    public function __construct(private readonly VendingMachineRepositoryInterface $repository)
    {
    }

    public function handle(ServiceMachineCommand $command): VendingResult
    {
        $machine = $this->repository->get();
        $machine->service($command->coinBank, $command->inventory);
        $this->repository->save($machine);

        return VendingResult::error('Machine serviced.');
    }
}
