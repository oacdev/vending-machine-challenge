<?php

declare(strict_types=1);

namespace VendingMachine\Application\Command;

use VendingMachine\Domain\Product;

final readonly class SelectProductCommand
{
    public function __construct(
        public Product $product,
    ) {
    }
}
