<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Domain\Model\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\InvalidPriceException;
use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
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
            $product->updateDetails('Candle', 'candle', -5.0, $this->category());
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

        $product->updateDetails('Amber candle', 'amber-candle', 0.0, $category, $attributes);

        self::assertSame('Amber candle', $product->getName());
        self::assertSame('amber-candle', $product->getSlug());
        self::assertSame(0.0, $product->getPrice());
        self::assertSame($category, $product->getCategory());
        self::assertSame($attributes, $product->getAttributes());
    }

    public function testANewProductIsADraft(): void
    {
        $product = new Product('id', 'Candle', 'candle', 10.0, $this->category());

        self::assertSame(ProductStatus::DRAFT, $product->getStatus());
        self::assertFalse($product->isPublished());
    }

    public function testAProductIsPublishedThenTakenBackToDraft(): void
    {
        $product = new Product('id', 'Candle', 'candle', 10.0, $this->category());

        $product->changeStatus(ProductStatus::PUBLISHED);
        self::assertTrue($product->isPublished());

        $product->changeStatus(ProductStatus::DRAFT);
        self::assertSame(ProductStatus::DRAFT, $product->getStatus());
    }

    public function testArchivingKeepsTheProductAndCanBeRepeated(): void
    {
        $product = new Product('id', 'Candle', 'candle', 10.0, $this->category(), status: ProductStatus::PUBLISHED);

        $product->archive();
        $product->archive();

        self::assertSame(ProductStatus::ARCHIVED, $product->getStatus());
        self::assertFalse($product->isPublished());
        self::assertSame('Candle', $product->getName());
    }

    public function testAnArchivedProductCanBeRestored(): void
    {
        $product = new Product('id', 'Candle', 'candle', 10.0, $this->category(), status: ProductStatus::ARCHIVED);

        $product->changeStatus(ProductStatus::PUBLISHED);

        self::assertTrue($product->isPublished());
    }

    private function category(): Category
    {
        return new Category('cat-1', 'Candles', 'candles');
    }
}
