<?php

declare(strict_types=1);

namespace VendingMachine\Domain;

final readonly class CoinBank
{
    /**
     * @param array<int, int> $coins Coin cent value → count
     */
    private function __construct(
        private array $coins,
    ) {
    }

    public static function empty(): self
    {
        return new self([
            Coin::Dollar->value => 0,
            Coin::Quarter->value => 0,
            Coin::Dime->value => 0,
            Coin::Nickel->value => 0,
        ]);
    }

    /**
     * @param array<int, int> $stock Coin cent value → count
     */
    public static function withStock(array $stock): self
    {
        $coins = [
            Coin::Dollar->value => 0,
            Coin::Quarter->value => 0,
            Coin::Dime->value => 0,
            Coin::Nickel->value => 0,
        ];

        foreach ($stock as $coinValue => $count) {
            $coins[$coinValue] = $count;
        }

        return new self($coins);
    }

    public function addCoin(Coin $coin): self
    {
        $coins = $this->coins;
        $coins[$coin->value]++;

        return new self($coins);
    }

    /**
     * @param array<Coin> $coinsToAdd
     */
    public function addCoins(array $coinsToAdd): self
    {
        $coins = $this->coins;

        foreach ($coinsToAdd as $coin) {
            $coins[$coin->value]++;
        }

        return new self($coins);
    }

    public function removeCoin(Coin $coin): self
    {
        $coins = $this->coins;
        $coins[$coin->value]--;

        return new self($coins);
    }

    /**
     * @param array<Coin> $coinsToRemove
     */
    public function removeCoins(array $coinsToRemove): self
    {
        $coins = $this->coins;

        foreach ($coinsToRemove as $coin) {
            $coins[$coin->value]--;
        }

        return new self($coins);
    }

    /**
     * Greedy change algorithm: largest denomination first.
     *
     * @return array<Coin>|null Coins for change, empty array for exact change, null if impossible
     */
    public function makeChange(int $amount): ?array
    {
        if ($amount === 0) {
            return [];
        }

        $available = $this->coins;
        $change = [];
        $remaining = $amount;

        $denominations = [Coin::Dollar, Coin::Quarter, Coin::Dime, Coin::Nickel];

        foreach ($denominations as $coin) {
            while ($remaining >= $coin->value && $available[$coin->value] > 0) {
                $change[] = $coin;
                $available[$coin->value]--;
                $remaining -= $coin->value;
            }
        }

        if ($remaining !== 0) {
            return null;
        }

        return $change;
    }

    public function totalInCents(): int
    {
        $total = 0;

        foreach ($this->coins as $value => $count) {
            $total += $value * $count;
        }

        return $total;
    }

    public function getCount(Coin $coin): int
    {
        return $this->coins[$coin->value];
    }

    public function isEmpty(): bool
    {
        foreach ($this->coins as $count) {
            if ($count > 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, int> Coin cent value → count
     */
    public function toArray(): array
    {
        return $this->coins;
    }
}
