<?php

namespace App\Tests\Unit\Ecommerce\Application\Mapper;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Application\Mapper\GetProductsFilterMapper;
use PHPUnit\Framework\TestCase;

class GetProductsFilterMapperTest extends TestCase
{
    private GetProductsFilterMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GetProductsFilterMapper();
    }

    public function testMapSuccessfullyMapsData(): void
    {
        $data = [
            'category_id' => 'cat-123',
            'min_price' => '10.50',
            'max_price' => '100.00',
            'scent' => 'lavender',
            'size' => 'large',
        ];

        $dto = $this->mapper->map($data);

        $this->assertInstanceOf(GetProductsFilterDto::class, $dto);
        $this->assertEquals('cat-123', $dto->categoryId);
        $this->assertEquals(10.5, $dto->minPrice);
        $this->assertEquals(100.0, $dto->maxPrice);
        $this->assertEquals('lavender', $dto->scent);
        $this->assertEquals('large', $dto->size);
    }

    public function testMapHandlesMissingDataWithNulls(): void
    {
        $data = [];

        $dto = $this->mapper->map($data);

        $this->assertInstanceOf(GetProductsFilterDto::class, $dto);
        $this->assertNull($dto->categoryId);
        $this->assertNull($dto->minPrice);
        $this->assertNull($dto->maxPrice);
        $this->assertNull($dto->scent);
        $this->assertNull($dto->size);
    }

    public function testMapHandlesPartialData(): void
    {
        $data = [
            'category_id' => 'cat-456',
            'min_price' => '5.00',
        ];

        $dto = $this->mapper->map($data);

        $this->assertEquals('cat-456', $dto->categoryId);
        $this->assertEquals(5.0, $dto->minPrice);
        $this->assertNull($dto->maxPrice);
        $this->assertNull($dto->scent);
        $this->assertNull($dto->size);
    }
}