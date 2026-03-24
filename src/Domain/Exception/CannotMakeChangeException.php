<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Exception;

use DomainException;

final class CannotMakeChangeException extends DomainException
{
    public function __construct(
        public readonly int $changeRequired,
    ) {
        parent::__construct(
            sprintf('Cannot make change for %d cents.', $changeRequired),
        );
    }
}
