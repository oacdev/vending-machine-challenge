# Vending Machine by @oacdev - Oriol Alonso Clar

A vending machine simulator built with **Domain-Driven Design** and **Hexagonal Architecture** in PHP 8.3.

The domain model is framework-agnostic. The only adapter is a Symfony Console CLI, proving that the architecture allows swapping infrastructure without touching business logic.

## Architecture

```
src/
├── Domain/                        # Pure domain: no framework deps, no I/O
│   ├── Coin.php                   # Enum: Nickel(5), Dime(10), Quarter(25), Dollar(100)
│   ├── Product.php                # Enum: Water(65), Juice(100), Soda(150)
│   ├── CoinBank.php               # Immutable VO: coin pool + greedy change algorithm
│   ├── Inventory.php              # Immutable VO: product stock
│   ├── VendingMachine.php         # Aggregate root: all state + operations
│   ├── VendingResult.php          # Response DTO
│   ├── VendingResultType.php      # Enum: Dispensed, CoinsReturned, Error
│   └── Exception/
│       ├── InsufficientFundsException.php
│       ├── OutOfStockException.php
│       └── CannotMakeChangeException.php
├── Application/
│   └── VendingMachineService.php  # Thin use-case orchestrator
└── Infrastructure/
    └── Cli/
        └── VendingMachineCommand.php  # Symfony Console interactive REPL
```

**Layer rules (enforced by Deptrac):**

- **Domain** depends on nothing external
- **Application** depends on Domain only
- **Infrastructure** depends on Application + Domain

## Requirements

- PHP 8.3+
- Composer
- Docker (optional)

## Quick Start

### With Docker

```bash
docker compose run --rm app
```

### Local

```bash
composer install
php bin/vending-machine
```

## Usage

The machine accepts coins (`0.05`, `0.10`, `0.25`, `1.00`), product commands (`GET-WATER`, `GET-JUICE`, `GET-SODA`), `RETURN-COIN`, `SERVICE`, and `EXIT`.

### Example 1 — Buy soda with exact change

```
> 1.00
Inserted 1.00. Current balance: 1.00.
> 0.25
Inserted 0.25. Current balance: 1.25.
> 0.25
Inserted 0.25. Current balance: 1.50.
> GET-SODA
Dispensed SODA.
```

### Example 2 — Return coins

```
> 0.10
Inserted 0.10. Current balance: 0.10.
> 0.10
Inserted 0.10. Current balance: 0.20.
> RETURN-COIN
Returned: $0.10, $0.10
```

### Example 3 — Buy water with change

```
> 1.00
Inserted 1.00. Current balance: 1.00.
> GET-WATER
Dispensed WATER.
Change: $0.25, $0.10
```

## Tests

```bash
# All tests
vendor/bin/phpunit

# Unit tests only
vendor/bin/phpunit --testsuite Unit

# Integration tests only
vendor/bin/phpunit --testsuite Integration
```

## Static Analysis

```bash
# PHPStan (level 8)
vendor/bin/phpstan analyse

# Deptrac (layer dependency rules)
vendor/bin/deptrac analyse

# PHP-CS-Fixer
vendor/bin/php-cs-fixer fix --dry-run --diff

# All checks at once
composer qa
```

## Design Decisions

### Integer cents for money

All monetary values are stored as integer cents internally. `Coin::Quarter` has value `25`, not `0.25`. Decimal formatting happens only in the CLI presentation layer. This eliminates floating-point rounding issues entirely.

### Immutable value objects

`CoinBank` and `Inventory` are immutable. Every mutation returns a new instance. This makes state transitions explicit and rollback trivial — if a purchase fails mid-way, we simply don't assign the new state.

### Change algorithm: coins merge before calculation

When a user buys a product, their inserted coins are added to the `CoinBank` **before** calculating change. This matters because the user's own coins might be needed to make change. If the change calculation fails, the merge is rolled back (the updated bank was never persisted).

### Greedy change algorithm

Change is calculated greedily: largest denomination first (Dollar → Quarter → Dime → Nickel). This is correct for the coin set `{5, 10, 25, 100}` because it forms a **canonical system** — the greedy algorithm always produces the optimal (minimum coins) solution.

For non-canonical denomination sets, dynamic programming would be needed. The greedy approach was chosen because it is correct for the given coins and simpler to reason about.

### CLI over REST

The challenge leaves the interface open. A CLI adapter proves hexagonal architecture just as well as HTTP — the domain and application layers have zero knowledge of the adapter. Adding a REST controller would require one new file and zero domain changes.

## Edge Cases

| Scenario | Behavior |
|---|---|
| Exact change | Purchase succeeds, no change returned |
| Overpayment | Change calculated via greedy algorithm |
| Cannot make change | Purchase rejected, all inserted coins returned |
| Out of stock | Error message, coins remain inserted |
| Insufficient funds | Error message, coins remain inserted |
| Unknown product | Error message |
| Zero change needed | Returns empty array (not null) |
