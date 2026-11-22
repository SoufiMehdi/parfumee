<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\DTO\Catalog;

use App\Ecommerce\Application\DTO\Catalog\ProductStatusFilterDto;
use App\Ecommerce\Domain\Exception\Catalog\InvalidProductStatusException;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use PHPUnit\Framework\TestCase;

final class ProductStatusFilterDtoTest extends TestCase
{
    public function testWithoutStatusTheArchivedProductsAreSetAside(): void
    {
        self::assertSame([ProductStatus::DRAFT, ProductStatus::PUBLISHED], (new ProductStatusFilterDto())->statuses());
    }

    public function testAStatusAsksForThatStatusOnly(): void
    {
        self::assertSame([ProductStatus::ARCHIVED], (new ProductStatusFilterDto('archived'))->statuses());
    }

    public function testAnUnknownStatusThatEscapedTheValidationIsAValidationError(): void
    {
        $this->expectException(InvalidProductStatusException::class);
        $this->expectExceptionMessage('"deleted" is not a product status: draft, published or archived.');

        (new ProductStatusFilterDto('deleted'))->statuses();
    }
}
