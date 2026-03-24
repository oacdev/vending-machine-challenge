<?php

declare(strict_types=1);

namespace VendingMachine\Domain\Exception;

use DomainException;

final class InsufficientFundsException extends DomainException
{
    public function __construct(
        public readonly int $insertedCents,
        public readonly int $requiredCents,
    ) {
        parent::__construct(
            sprintf(
                'Insufficient funds: inserted %d cents, but %s costs %d cents.',
                $insertedCents,
                $requiredCents,
                $requiredCents,
            ),
        );
    }
}
