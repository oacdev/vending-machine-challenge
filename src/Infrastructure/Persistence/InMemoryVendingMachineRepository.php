<?php

declare(strict_types=1);

namespace VendingMachine\Infrastructure\Persistence;

use VendingMachine\Domain\VendingMachine;
use VendingMachine\Domain\VendingMachineRepositoryInterface;

final class InMemoryVendingMachineRepository implements VendingMachineRepositoryInterface
{
    public function __construct(private VendingMachine $machine)
    {
    }

    public function get(): VendingMachine
    {
        return $this->machine;
    }

    public function save(VendingMachine $machine): void
    {
        $this->machine = $machine;
    }
}
