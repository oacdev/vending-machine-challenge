<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Exception;

use DomainException;
use VendingMachine\Domain\Product;

final class OutOfStockException extends DomainException
{
    public function __construct(
        public readonly Product $product,
    ) {
        parent::__construct(
            sprintf('%s is out of stock.', $product->label()),
        );
    }
}
