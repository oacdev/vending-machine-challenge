<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

enum VendingResultType
{
    case Dispensed;
    case CoinsReturned;
    case Error;
}
