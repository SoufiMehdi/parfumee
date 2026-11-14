<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Domain\Model\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\InvalidPriceException;
use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    public function testANegativePriceIsRefusedAtCreation(): void
    {
        $this->expectException(InvalidPriceException::class);

        new Product('id', 'Candle', 'candle', -0.01, $this->category());
    }

    public function testANegativePriceIsRefusedOnUpdateAndTheProductIsLeftUntouched(): void
    {
        $product = new Product('id', 'Candle', 'candle', 10.0, $this->category());

        try {
            $product->updateDetails('Candle', -5.0, $this->category());
            self::fail('A negative price must be refused.');
        } catch (InvalidPriceException) {
        }

        self::assertSame(10.0, $product->getPrice());
    }

    public function testUpdateDetailsChangesNamePriceCategoryAndAttributes(): void
    {
        $product = new Product('id', 'Candle', 'candle', 10.0, $this->category());
        $category = new Category('cat-2', 'Gifts', 'gifts');
        $attributes = new Attribute(weight: 180.0);

        $product->updateDetails('Amber candle', 0.0, $category, $attributes);

        self::assertSame('Amber candle', $product->getName());
        self::assertSame(0.0, $product->getPrice());
        self::assertSame($category, $product->getCategory());
        self::assertSame($attributes, $product->getAttributes());
    }

    private function category(): Category
    {
        return new Category('cat-1', 'Candles', 'candles');
    }
}
