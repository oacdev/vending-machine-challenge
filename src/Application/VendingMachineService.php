<?php

declare(strict_types=1);

namespace VendingMachine\Application;

use VendingMachine\Domain\Coin;
use VendingMachine\Domain\CoinBank;
use VendingMachine\Domain\Exception\CannotMakeChangeException;
use VendingMachine\Domain\Exception\InsufficientFundsException;
use VendingMachine\Domain\Exception\OutOfStockException;
use VendingMachine\Domain\Inventory;
use VendingMachine\Domain\Product;
use VendingMachine\Domain\VendingMachine;
use VendingMachine\Domain\VendingResult;

final class VendingMachineService
{
    private VendingMachine $machine;

    public function __construct(VendingMachine $machine)
    {
        $this->machine = $machine;
    }

    public function handleInput(string $input): VendingResult
    {
        $input = strtoupper(trim($input));

        if ($input === 'RETURN-COIN') {
            $coins = $this->machine->returnCoins();

            return VendingResult::coinsReturned($coins);
        }

        if ($input === 'SERVICE') {
            $this->machine->service(
                CoinBank::withStock([
                    Coin::Nickel->value => 10,
                    Coin::Dime->value => 10,
                    Coin::Quarter->value => 10,
                    Coin::Dollar->value => 10,
                ]),
                Inventory::default(5),
            );

            return VendingResult::error('Machine serviced.');
        }

        if (str_starts_with($input, 'GET-')) {
            $productName = strtolower(substr($input, 4));
            $product = Product::tryFrom($productName);

            if ($product === null) {
                return VendingResult::error(sprintf('Unknown product: %s.', $productName));
            }

            try {
                return $this->machine->selectProduct($product);
            } catch (OutOfStockException $e) {
                return VendingResult::error($e->getMessage());
            } catch (InsufficientFundsException $e) {
                return VendingResult::error($e->getMessage());
            } catch (CannotMakeChangeException) {
                $coins = $this->machine->returnCoins();

                return VendingResult::coinsReturned($coins);
            }
        }

        $coin = $this->parseCoin($input);

        if ($coin !== null) {
            $this->machine->insertCoin($coin);

            return VendingResult::error(sprintf('Inserted %s. Current balance: %s.', $coin->label(), number_format($this->machine->getInsertedAmount() / 100, 2)));
        }

        return VendingResult::error(sprintf('Unknown command: %s.', $input));
    }

    public function getMachine(): VendingMachine
    {
        return $this->machine;
    }

    private function parseCoin(string $input): ?Coin
    {
        return match ($input) {
            '0.05' => Coin::Nickel,
            '0.10' => Coin::Dime,
            '0.25' => Coin::Quarter,
            '1.00' => Coin::Dollar,
            default => null,
        };
    }
}
