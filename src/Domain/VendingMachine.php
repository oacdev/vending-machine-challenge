<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

use VendingMachine\Domain\Exception\CannotMakeChangeException;
use VendingMachine\Domain\Exception\InsufficientFundsException;
use VendingMachine\Domain\Exception\OutOfStockException;

final class VendingMachine
{
    private CoinBank $coinBank;
    private Inventory $inventory;
    private int $insertedAmountInCents = 0;

    /** @var array<Coin> */
    private array $insertedCoins = [];

    public function __construct(CoinBank $coinBank, Inventory $inventory)
    {
        $this->coinBank = $coinBank;
        $this->inventory = $inventory;
    }

    public function insertCoin(Coin $coin): void
    {
        $this->insertedCoins[] = $coin;
        $this->insertedAmountInCents += $coin->value;
    }

    /**
     * @return array<Coin>
     */
    public function returnCoins(): array
    {
        $coins = $this->insertedCoins;
        $this->insertedCoins = [];
        $this->insertedAmountInCents = 0;

        return $coins;
    }

    public function selectProduct(Product $product): VendingResult
    {
        if (!$this->inventory->hasProduct($product)) {
            throw new OutOfStockException($product);
        }

        $price = $product->priceInCents();

        if ($this->insertedAmountInCents < $price) {
            throw new InsufficientFundsException($this->insertedAmountInCents, $price);
        }

        $overpayment = $this->insertedAmountInCents - $price;
        $change = [];

        if ($overpayment > 0) {
            $updatedBank = $this->coinBank->addCoins($this->insertedCoins);
            $change = $updatedBank->makeChange($overpayment);

            if ($change === null) {
                throw new CannotMakeChangeException($overpayment);
            }

            $this->coinBank = $updatedBank->removeCoins($change);
        } else {
            $this->coinBank = $this->coinBank->addCoins($this->insertedCoins);
        }

        $this->inventory = $this->inventory->dispense($product);
        $this->insertedCoins = [];
        $this->insertedAmountInCents = 0;

        return VendingResult::dispensed($product, $change);
    }

    public function service(CoinBank $coinBank, Inventory $inventory): void
    {
        $this->coinBank = $coinBank;
        $this->inventory = $inventory;
        $this->insertedCoins = [];
        $this->insertedAmountInCents = 0;
    }

    public function getInsertedAmount(): int
    {
        return $this->insertedAmountInCents;
    }

    /**
     * @return array{coinBank: CoinBank, inventory: Inventory, insertedAmount: int}
     */
    public function getState(): array
    {
        return [
            'coinBank' => $this->coinBank,
            'inventory' => $this->inventory,
            'insertedAmount' => $this->insertedAmountInCents,
        ];
    }
}
