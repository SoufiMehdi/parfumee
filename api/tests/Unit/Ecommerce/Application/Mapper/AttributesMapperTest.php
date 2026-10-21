<?php

namespace App\Tests\Unit\Ecommerce\Application\Mapper;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\Mapper\AttributesMapper;
use App\Ecommerce\Domain\Model\Catalog\Attribute;
use PHPUnit\Framework\TestCase;

class AttributesMapperTest extends TestCase
{
    private AttributesMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new AttributesMapper();
    }

    public function testFromDtoMapsCorrectly(): void
    {
        $dto = new CreateProductDto(
            name: 'Test Product',
            price: 10.0,
            categoryId: 'cat-1',
            attributes: [
                'weight' => 1.5,
                'fragrance' => 'Floral',
                'size' => 'Large',
                'description' => 'A nice product',
                'presentation' => 'Box'
            ]
        );

        $attribute = $this->mapper->fromDto($dto);

        $this->assertInstanceOf(Attribute::class, $attribute);
        $this->assertEquals(1.5, $attribute->getWeight());
        $this->assertEquals('Floral', $attribute->getFragrance());
        $this->assertEquals('Large', $attribute->getSize());
        $this->assertEquals('A nice product', $attribute->getDescription());
        $this->assertEquals('Box', $attribute->getPresentation());
    }

    public function testToDtoMapsCorrectly(): void
    {
        $attribute = $this->createMock(Attribute::class);
        $attribute->method('getWeight')->willReturn(1.5);
        $attribute->method('getFragrance')->willReturn('Floral');
        $attribute->method('getSize')->willReturn('Large');
        $attribute->method('getDescription')->willReturn('A nice product');
        $attribute->method('getPresentation')->willReturn('Box');

        $result = $this->mapper->toDto($attribute);

        $this->assertIsArray($result);
        $this->assertEquals([
            'weight' => 1.5,
            'fragrance' => 'Floral',
            'size' => 'Large',
            'description' => 'A nice product',
            'presentation' => 'Box'
        ], $result);
    }

    public function testFromArrayMapsCorrectly(): void
    {
        $data = [
            'weight' => 2.0,
            'fragrance' => 'Woody',
            'size' => 'Small',
            'description' => 'Another product',
            'presentation' => 'Bag'
        ];

        $attribute = $this->mapper->fromArray($data);

        $this->assertInstanceOf(Attribute::class, $attribute);
        $this->assertEquals(2.0, $attribute->getWeight());
        $this->assertEquals('Woody', $attribute->getFragrance());
        $this->assertEquals('Small', $attribute->getSize());
        $this->assertEquals('Another product', $attribute->getDescription());
        $this->assertEquals('Bag', $attribute->getPresentation());
    }

  /*  public function testFromDtoHandlesMissingValues(): void
    {
        $dto = new CreateProductDto(
            name: 'Test',
            price: 0.0,
            categoryId: 'cat',
            attributes: [
                'weight' => null,
                'fragrance' => null,
                'size' => 'Medium',
                'description' => null,
                'presentation' => null
            ]
        );

        $attribute = $this->mapper->fromDto($dto);

        $this->assertNull($attribute->getWeight());
        $this->assertNull($attribute->getFragrance());
        $this->assertEquals('Medium', $attribute->getSize());
        $this->assertNull($attribute->getDescription());
        $this->assertNull($attribute->getPresentation());
    }*/
}