<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

final readonly class VendingResult
{
    /**
     * @param array<Coin> $change
     */
    private function __construct(
        public VendingResultType $type,
        public ?Product $product,
        public array $change,
        public string $message,
    ) {
    }

    /**
     * @param array<Coin> $change
     */
    public static function dispensed(Product $product, array $change): self
    {
        return new self(
            type: VendingResultType::Dispensed,
            product: $product,
            change: $change,
            message: sprintf('Dispensed %s.', $product->label()),
        );
    }

    /**
     * @param array<Coin> $coins
     */
    public static function coinsReturned(array $coins): self
    {
        return new self(
            type: VendingResultType::CoinsReturned,
            product: null,
            change: $coins,
            message: 'Coins returned.',
        );
    }

    public static function error(string $message): self
    {
        return new self(
            type: VendingResultType::Error,
            product: null,
            change: [],
            message: $message,
        );
    }
}
