<?php

declare(strict_types=1);

namespace VendingMachine\Application\Command;

use VendingMachine\Domain\CoinBank;
use VendingMachine\Domain\Inventory;

final readonly class ServiceMachineCommand
{
    public function __construct(
        public CoinBank $coinBank,
        public Inventory $inventory,
    ) {
    }
}
