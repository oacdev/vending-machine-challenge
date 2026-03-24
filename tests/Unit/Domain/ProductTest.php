<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use VendingMachine\Domain\Product;

final class ProductTest extends TestCase
{
    public function test_product_values(): void
    {
        self::assertSame('water', Product::Water->value);
        self::assertSame('juice', Product::Juice->value);
        self::assertSame('soda', Product::Soda->value);
    }

    public function test_price_in_cents(): void
    {
        self::assertSame(65, Product::Water->priceInCents());
        self::assertSame(100, Product::Juice->priceInCents());
        self::assertSame(150, Product::Soda->priceInCents());
    }

    public function test_label_returns_uppercase(): void
    {
        self::assertSame('WATER', Product::Water->label());
        self::assertSame('JUICE', Product::Juice->label());
        self::assertSame('SODA', Product::Soda->label());
    }

    public function test_cases_returns_all_products(): void
    {
        $cases = Product::cases();

        self::assertCount(3, $cases);
    }
}
