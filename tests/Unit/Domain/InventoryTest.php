<?php

declare(strict_types=1);

namespace VendingMachine\Tests\Unit\Domain;

use PHPUnit\Framework\TestCase;
use VendingMachine\Domain\Inventory;
use VendingMachine\Domain\Product;

final class InventoryTest extends TestCase
{
    public function test_default_inventory(): void
    {
        $inventory = Inventory::default(5);

        self::assertSame(5, $inventory->getCount(Product::Water));
        self::assertSame(5, $inventory->getCount(Product::Juice));
        self::assertSame(5, $inventory->getCount(Product::Soda));
    }

    public function test_empty_inventory(): void
    {
        $inventory = Inventory::empty();

        self::assertSame(0, $inventory->getCount(Product::Water));
        self::assertSame(0, $inventory->getCount(Product::Juice));
        self::assertSame(0, $inventory->getCount(Product::Soda));
    }

    public function test_with_stock(): void
    {
        $inventory = Inventory::withStock([
            Product::Water->value => 3,
            Product::Soda->value => 7,
        ]);

        self::assertSame(3, $inventory->getCount(Product::Water));
        self::assertSame(0, $inventory->getCount(Product::Juice));
        self::assertSame(7, $inventory->getCount(Product::Soda));
    }

    public function test_has_product(): void
    {
        $inventory = Inventory::withStock([
            Product::Water->value => 1,
            Product::Juice->value => 0,
        ]);

        self::assertTrue($inventory->hasProduct(Product::Water));
        self::assertFalse($inventory->hasProduct(Product::Juice));
    }

    public function test_dispense_returns_new_instance(): void
    {
        $inventory = Inventory::default(3);
        $newInventory = $inventory->dispense(Product::Water);

        self::assertSame(3, $inventory->getCount(Product::Water));
        self::assertSame(2, $newInventory->getCount(Product::Water));
    }

    public function test_dispense_does_not_affect_other_products(): void
    {
        $inventory = Inventory::default(5);
        $newInventory = $inventory->dispense(Product::Juice);

        self::assertSame(5, $newInventory->getCount(Product::Water));
        self::assertSame(4, $newInventory->getCount(Product::Juice));
        self::assertSame(5, $newInventory->getCount(Product::Soda));
    }

    public function test_to_array(): void
    {
        $inventory = Inventory::withStock([
            Product::Water->value => 2,
            Product::Juice->value => 4,
            Product::Soda->value => 6,
        ]);

        $array = $inventory->toArray();

        self::assertSame(2, $array[Product::Water->value]);
        self::assertSame(4, $array[Product::Juice->value]);
        self::assertSame(6, $array[Product::Soda->value]);
    }
}
