<?php

declare(strict_types=1);

namespace VendingMachine\Application\Command;

use VendingMachine\Domain\Coin;

final readonly class InsertCoinCommand
{
    public function __construct(
        public Coin $coin,
    ) {
    }
}
