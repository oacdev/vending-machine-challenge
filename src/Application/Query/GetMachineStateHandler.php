<?php

declare(strict_types=1);

namespace VendingMachine\Application\Query;

use VendingMachine\Domain\CoinBank;
use VendingMachine\Domain\Inventory;
use VendingMachine\Domain\VendingMachineRepositoryInterface;

final class GetMachineStateHandler
{
    public function __construct(private readonly VendingMachineRepositoryInterface $repository)
    {
    }

    /**
     * @return array{coinBank: CoinBank, inventory: Inventory, insertedAmount: int}
     */
    public function handle(GetMachineStateQuery $query): array
    {
        return $this->repository->get()->getState();
    }
}
