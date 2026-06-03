<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

interface VendingMachineRepositoryInterface
{
    public function get(): VendingMachine;

    public function save(VendingMachine $machine): void;
}
