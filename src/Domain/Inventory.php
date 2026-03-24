<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

final readonly class Inventory
{
    /**
     * @param array<string, int> $stock Product value → count
     */
    private function __construct(
        private array $stock,
    ) {
    }

    public static function empty(): self
    {
        $stock = [];
        foreach (Product::cases() as $product) {
            $stock[$product->value] = 0;
        }

        return new self($stock);
    }

    public static function default(int $quantity = 5): self
    {
        $stock = [];
        foreach (Product::cases() as $product) {
            $stock[$product->value] = $quantity;
        }

        return new self($stock);
    }

    /**
     * @param array<string, int> $stock Product value → count
     */
    public static function withStock(array $stock): self
    {
        $base = [];
        foreach (Product::cases() as $product) {
            $base[$product->value] = $stock[$product->value] ?? 0;
        }

        return new self($base);
    }

    public function hasProduct(Product $product): bool
    {
        return $this->stock[$product->value] > 0;
    }

    public function getCount(Product $product): int
    {
        return $this->stock[$product->value];
    }

    public function dispense(Product $product): self
    {
        $stock = $this->stock;
        $stock[$product->value]--;

        return new self($stock);
    }

    /**
     * @return array<string, int> Product value → count
     */
    public function toArray(): array
    {
        return $this->stock;
    }
}
