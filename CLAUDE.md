# Vending Machine — Senior Backend Challenge

## Project overview

A vending machine modelled with DDD, hexagonal architecture, and a CLI adapter (Symfony Console standalone).
PHP 8.3+, PHPUnit, PHPStan, Deptrac, PHP-CS-Fixer, Docker.

---

## CRITICAL RULES — READ BEFORE WRITING ANY CODE

1. **NEVER reference the company name "Holded" anywhere in the code, comments, commits, README, or any file.** This is an explicit filter in the challenge. Violation = automatic fail.
2. **Commit after EACH logical step.** Each commit below is a separate `git add -A && git commit`. Do NOT batch multiple steps into one commit. Do NOT continue to the next step without committing the current one first. The evaluator will read the git log as a story of how you built the project.
3. **Commit messages must be conventional commits**: `feat:`, `test:`, `chore:`, `docs:`.
4. **All money is handled in integer cents internally.** NEVER use float for money. Coin enum values are in cents (5, 10, 25, 100). Decimal formatting (0.05, 0.10, etc.) happens ONLY in the CLI presentation layer (Infrastructure). If you see a float anywhere in Domain or Application, it's a bug.
5. **Domain layer has ZERO dependencies on infrastructure or application.** No `use Symfony\...` in Domain. Deptrac enforces this. If Deptrac reports a violation, fix it before moving on.
6. **Tests use real assertions, no mocks for domain objects.** Test behaviour, not implementation. Instantiate real objects.
7. **After EVERY commit, run `vendor/bin/phpstan analyse` and `vendor/bin/phpunit` (once tests exist).** Do not commit code that breaks static analysis or tests.

---

## COMMIT WORKFLOW — MANDATORY

**This is NOT optional. Follow this exact workflow for EVERY step:**

```
1. Write the code for the current step
2. Run vendor/bin/phpstan analyse → must pass
3. Run vendor/bin/phpunit (if tests exist) → must pass
4. Run vendor/bin/php-cs-fixer fix → apply fixes
5. git add -A
6. git commit -m "<message from plan>"
7. STOP. Confirm what was done. Only then proceed to next step.
```

**Do NOT:**
- Write code for steps 2-5 and then do one big commit
- Skip running phpstan/phpunit between commits
- Combine a feat commit with a test commit
- Refactor something from a previous step inside a later commit (make a separate `refactor:` commit)

**The evaluator WILL run `git log --oneline` and `git log --stat`. They want to see:**
- 15-20 small, focused commits
- Each commit compiles and passes analysis
- A readable narrative: scaffold → domain model → tests → application → infrastructure → polish

---

## Architecture

```
src/
├── Domain/                    # Pure domain: no framework deps, no I/O, no use statements outside Domain
│   ├── Coin.php               # Enum: Nickel(5), Dime(10), Quarter(25), Dollar(100)
│   ├── Product.php            # Enum: Water(65), Juice(100), Soda(150)
│   ├── CoinBank.php           # Value Object: tracks coin quantities, makes change (IMMUTABLE)
│   ├── Inventory.php          # Value Object: tracks product quantities (IMMUTABLE)
│   ├── VendingMachine.php     # Aggregate Root: all state + operations
│   ├── VendingResult.php      # Response DTO: what happened after an action
│   ├── VendingResultType.php  # Enum: DISPENSED, COINS_RETURNED, ERROR
│   └── Exception/
│       ├── InsufficientFundsException.php
│       ├── OutOfStockException.php
│       └── CannotMakeChangeException.php
├── Application/
│   └── VendingMachineService.php  # Thin use-case orchestrator
└── Infrastructure/
    └── Cli/
        └── VendingMachineCommand.php  # Symfony Console adapter (ONLY place that uses Symfony)
```

---

## CRITICAL DESIGN DECISIONS — DO NOT DEVIATE

### 1. The change algorithm: coins go INTO the bank BEFORE calculating change

This is the single most important implementation detail. Get it wrong and the machine fails on valid purchases.

**The correct flow in `selectProduct()`:**

```
User inserts 1.00 (Dollar). Buys Water (0.65). Change needed = 0.35.

CORRECT ORDER:
1. Validate inventory (has Water? yes)
2. Validate funds (inserted 100 >= price 65? yes)
3. Calculate overpayment: 100 - 65 = 35 cents
4. If overpayment > 0:
   a. Add ALL inserted coins to CoinBank → bank now includes the Dollar
   b. Try makeChange(35) from the UPDATED bank
   c. If makeChange returns null → ROLLBACK: remove inserted coins from bank, throw CannotMakeChangeException
   d. If makeChange succeeds → remove those change coins from bank
5. If overpayment == 0:
   a. Just add inserted coins to bank (they become future change)
6. Dispense product from inventory
7. Reset inserted coins/amount to zero
8. Return VendingResult::dispensed(product, change)

WRONG ORDER (common mistake):
1. Try makeChange(35) from bank WITHOUT the Dollar
2. Bank only has [nickel:5, dime:5, quarter:5] → might fail unnecessarily
3. Then add coins after → too late, we already rejected the purchase
```

**Why this matters:** If the user inserts coins that could serve as their own change source, they should be available. Adding coins to the bank first makes this possible.

### 2. On CannotMakeChangeException: return the user's coins

When change cannot be made, the purchase MUST NOT proceed. The user gets their money back. The machine state returns to what it was before. The inserted coins do NOT stay in the bank.

Sequence on failure:
```
1. Add inserted coins to bank (step 4a above)
2. makeChange fails → null
3. ROLLBACK: remove inserted coins from bank (or just don't persist the updated bank)
4. Throw CannotMakeChangeException — Application layer catches and returns the user's coins
```

The simplest way to handle rollback: don't assign `$this->coinBank = $updatedBank` until ALL validations pass. Work with a local `$updatedBank` variable and only commit at the end.

### 3. Integer cents everywhere, decimal only in CLI

```php
// DOMAIN — always int
enum Coin: int {
    case Nickel  = 5;
    case Dime    = 10;
    case Quarter = 25;
    case Dollar  = 100;
}

// The label() method on Coin can return "0.25" for display — but internally it's always 25.
```

### 4. Immutable Value Objects

CoinBank and Inventory are IMMUTABLE. Every mutation method returns a NEW instance:

```php
// CORRECT
$newBank = $bank->addCoin(Coin::Quarter);  // $bank is unchanged, $newBank has the extra quarter

// WRONG
$bank->addCoin(Coin::Quarter);  // mutating in place — NOT what we want
```

### 5. VendingMachine is the Aggregate Root

VendingMachine holds mutable state. It's the aggregate root — all operations go through it.

**Internal state (important — get this right):**
```
- CoinBank $coinBank              → the machine's change pool (NOT including currently inserted coins)
- Inventory $inventory            → product stock
- int $insertedAmountInCents = 0  → running total of what user has inserted THIS SESSION
- array<Coin> $insertedCoins = [] → actual coin objects inserted (for return and for merging on purchase)
```

**Key distinction:** `$insertedCoins` are NOT in `$coinBank`. They are separate. On `insertCoin()` we add to `$insertedCoins` and `$insertedAmountInCents` only. On successful `selectProduct()` we merge them into `$coinBank`. On `returnCoins()` we give them back and clear both.

---

## KNOWN GOTCHAS — READ BEFORE CODING

### PHP-CS-Fixer `final_class` rule and Enums

The `.php-cs-fixer.dist.php` has `'final_class' => true`. PHP enums are implicitly final — CS-Fixer may try to add `final` to enum declarations, which is a syntax error in PHP.

**Fix if this happens:** Change the rule to:
```php
'final_class' => ['consider_absent_docblock_as_internal_class' => false],
```
Or remove the `final_class` rule entirely and just manually write `final class` on all classes (not enums).

### PHPStan and backed enum `from()` / `tryFrom()`

PHPStan level 8 may complain about `Coin::from()` if you pass an int that doesn't match any case. Use `Coin::tryFrom()` and handle null, or use a custom `fromAmount()` static method with explicit match + throw.

### Deptrac `directory` collector paths

The deptrac.yaml uses regex paths. The `.*` is a regex wildcard, not a glob:
```yaml
- type: directory
  value: src/Domain/.*    # Regex: matches src/Domain/ and everything inside
```

### Symfony Console in Docker

The Dockerfile CMD runs `bin/vending-machine`. Ensure:
1. First line: `#!/usr/bin/env php`
2. Executable: `chmod +x bin/vending-machine`
3. Bootstrap: `require __DIR__ . '/../vendor/autoload.php';`
4. docker-compose has `stdin_open: true` and `tty: true` for interactive input

### Greedy change algorithm limitations

The greedy algorithm works for denominations {5, 10, 25, 100} because they form a "canonical" system. Document this in the README. If asked "what about non-canonical denominations?" the answer is: "You'd need dynamic programming. I chose greedy because it's correct for the given coin set and simpler to reason about."

---

## Commit plan — FOLLOW THIS EXACTLY

### Commit #1 — `chore: scaffold project structure and tooling`
**THIS COMMIT IS ALREADY DONE.** Start from here:

Run `composer install` to generate composer.lock. Then:
```bash
git add composer.lock
git commit -m "chore: add composer.lock"
```

### Commit #2 — `feat: add Coin and Product domain enums`
Files: src/Domain/Coin.php, src/Domain/Product.php

**Coin.php:**
- Backed enum `int`: Nickel=5, Dime=10, Quarter=25, Dollar=100
- `label(): string` → "0.05", "0.10", "0.25", "1.00"
- `static fromAmount(int $cents): self` → maps value to case, throws `\ValueError` on invalid

**Product.php:**
- Backed enum `string`: Water='water', Juice='juice', Soda='soda'
- `priceInCents(): int` → 65, 100, 150
- `label(): string` → "WATER", "JUICE", "SODA"

Verify: phpstan passes, cs-fixer passes.

### Commit #3 — `feat: implement CoinBank value object with change algorithm`
File: src/Domain/CoinBank.php

Immutable. Internal: `array<int, int>` (coin cent value → count).

Methods: `addCoin`, `addCoins`, `removeCoin`, `removeCoins` (all return `self`), `makeChange(int): ?array<Coin>`, `totalInCents(): int`, `getCount(Coin): int`, `isEmpty(): bool`, `toArray(): array`.

**makeChange algorithm — GREEDY, largest denomination first:**
```
Sort coins [Dollar, Quarter, Dime, Nickel] (descending by value)
For each denomination:
    Take as many as possible without exceeding remaining amount
    Subtract from remaining, decrease available count
If remaining != 0 → return null (impossible)
Return array of Coin objects used
```

### Commit #4 — `test: add CoinBank unit tests`
File: tests/Unit/Domain/CoinBankTest.php

Minimum test cases:
- Make change exact, multiple denominations, impossible, zero amount
- Greedy order verified (largest first)
- Add/remove coin
- Immutability (original unchanged after operations)
- Empty bank returns null
- Total calculation

### Commit #5 — `feat: implement Inventory value object`
File: src/Domain/Inventory.php

Immutable. Methods: `default()`, `withStock()`, `stock()`, `hasProduct()`, `dispense()`, `getCount()`.

### Commit #6 — `feat: implement domain exceptions`
Files: InsufficientFundsException.php, OutOfStockException.php, CannotMakeChangeException.php

All extend `\DomainException`. Each carries context (readonly public properties).

### Commit #7 — `feat: implement VendingResult response DTO`
Files: VendingResult.php, VendingResultType.php

VendingResultType enum: Dispensed, CoinsReturned, Error.
VendingResult: final readonly, static factories `dispensed()`, `coinsReturned()`, `error()`.

### Commit #8 — `feat: implement VendingMachine aggregate root`
File: src/Domain/VendingMachine.php

**THE CORE FILE.** Follow the CRITICAL DESIGN DECISIONS section above exactly.

Methods: `insertCoin()`, `returnCoins()`, `selectProduct()`, `service()`, `getInsertedAmount()`, `getState()`.

**VERIFY the selectProduct flow matches the documented order: add coins to bank FIRST, then calculate change.**

### Commit #9 — `test: add VendingMachine unit tests`
File: tests/Unit/Domain/VendingMachineTest.php

**MANDATORY tests — ALL of these:**
- Buy exact change, overpayment with change, insufficient funds, out of stock, cannot make change
- Return coins (with and without coins inserted)
- Multiple coins accumulate
- Service resets state
- Inserted coins added to bank after purchase
- Sequential purchases maintain state
- **THE 3 CHALLENGE EXAMPLES (exact values verified):**
  1. 1.00 + 0.25 + 0.25, GET-SODA → SODA, no change
  2. 0.10 + 0.10, RETURN-COIN → [0.10, 0.10]
  3. 1.00, GET-WATER → WATER, change = [0.25, 0.10] (greedy order)

### Commit #10 — `test: add Inventory, Product, and Coin unit tests`
Files: InventoryTest.php, CoinTest.php, ProductTest.php

### Commit #11 — `feat: implement VendingMachineService application layer`
File: src/Application/VendingMachineService.php

Thin layer: `handleInput(string): VendingResult`. Parses raw strings ("0.25", "GET-WATER", "RETURN-COIN", "SERVICE"), delegates to VendingMachine, catches exceptions → VendingResult::error().

### Commit #12 — `feat: implement Symfony Console CLI adapter`
Files: src/Infrastructure/Cli/VendingMachineCommand.php, bin/vending-machine

Interactive REPL. Display state, prompt, execute, show result, loop. Initial state: 5 of each product, 10 of each coin.

Don't forget `chmod +x bin/vending-machine`.

### Commit #13 — `test: add integration tests for full flows`
File: tests/Integration/VendingMachineFlowTest.php

The 3 challenge examples tested through VendingMachineService. Plus: sequential flows, error cases, service command.

### Commit #14 — `chore: verify static analysis and fix code style`
Run all tools, fix issues, commit fixes:
```bash
vendor/bin/php-cs-fixer fix
vendor/bin/phpstan analyse
vendor/bin/deptrac analyse
vendor/bin/phpunit
```

### Commit #15 — `chore: finalize Dockerfile and docker-compose`
Verify Docker works end-to-end. Fix and commit.

### Commit #16 — `docs: add comprehensive README`
Sections: Overview, Architecture, Requirements, Quick Start (Docker + local), Usage (3 examples), Tests, Static Analysis, Design Decisions, Edge Cases. NO company name.

### Commit #17+ — Optional extras (ONLY if everything above is done and green)
- Extract ChangeCalculator strategy
- JSON state persistence
- Transaction log
- Makefile

---

## PHP version and style

- PHP 8.3, `declare(strict_types=1);` everywhere
- Final classes by default, readonly properties
- Return types on ALL methods, no `mixed`
- One class per file, PSR-4

## Testing philosophy

- One test class per domain class
- No mocks — real objects
- Snake_case test names: `test_buy_water_with_exact_change()`
- Fresh state per test
- Data providers for parametric tests

## Deptrac layers

- Domain → NOTHING
- Application → Domain only
- Infrastructure → Application + Domain

## What NOT to do

- Float for money
- Database / persistence (unless optional extra)
- HTTP framework / REST
- Event sourcing, CQRS, message bus
- Reference the company name
- Giant commits
- `final` on enums
- Symfony imports in Domain
- Catch domain exceptions in Domain layer
- Unnecessary interfaces (VendingMachineInterface, etc.)

---

## Defense: why CLI instead of REST API

1. **Challenge says:** "How exactly the actions on the machine are driven is left intentionally vague and is up to the candidate."
2. **Hexagonal proof:** Domain and Application have zero adapter knowledge. Swap CLI for HTTP = one new adapter, zero domain changes.
3. **Time invested in what matters:** Tests, edge cases, static analysis > HTTP boilerplate.
4. **One-liner:** "Adding a REST endpoint to a well-modelled domain is an afternoon. Fixing a bad domain behind a REST API is weeks."
