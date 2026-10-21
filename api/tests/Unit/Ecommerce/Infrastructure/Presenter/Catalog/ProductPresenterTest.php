<?php

namespace Tests\Unit\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Application\Mapper\AttributesMapper;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class ProductPresenterTest extends TestCase
{
    private ProductPresenter $presenter;

    protected function setUp(): void
    {
        $this->presenter = new ProductPresenter();
    }

    public function testPresentReturnsFormattedArray(): void
    {
        $categoryMock = $this->createMock(Category::class);
        $categoryMock->method('getId')->willReturn('cat-123');
        $categoryMock->method('getName')->willReturn('Electronics');

        $attributes = new AttributesMapper();
        $attributesMock = $attributes->fromArray(
            [
                'weight' => 12.5,
                'fragrance' => 'floral',
                'size' => 'medium',
                'description' => 'A nice product',
                'presentation' => 'box',
            ]
            );

        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn('prod-123');
        $productMock->method('getName')->willReturn('Laptop');
        $productMock->method('getSlug')->willReturn('laptop');
        $productMock->method('getPrice')->willReturn(999.99);
        $productMock->method('getCategory')->willReturn($categoryMock);
        $productMock->method('getAttributes')->willReturn($attributesMock);

        $result = $this->presenter->present($productMock);
        
        $this->assertEquals([
            'id' => 'prod-123',
            'name' => 'Laptop',
            'slug' => 'laptop',
            'price' => 999.99,
            'category' => [
                'id' => 'cat-123',
                'name' => 'Electronics'
            ],
            'attributes' => [
                'weight' => 12.5,
                'fragrance' => 'floral',
                'size' => 'medium',
                'description' => 'A nice product',
                'presentation' => 'box',
            ],
            'pictures' => []
        ], $result);
    }

    public function testPresentCollectionReturnsArrayOfFormattedProducts(): void
    {
        $productMock = $this->createMock(Product::class);
        $categoryMock = $this->createMock(Category::class);
        $attributesMock = $this->createMock(ArrayCollection::class);

        $productMock->method('getId')->willReturn('p1');
        $productMock->method('getName')->willReturn('Item');
        $productMock->method('getSlug')->willReturn('item');
        $productMock->method('getPrice')->willReturn(10.0);
        $productMock->method('getCategory')->willReturn($categoryMock);
        $attributes = new AttributesMapper();
        $attributesMock = $attributes->fromArray(
            [
                'weight' => 12.5,
                'fragrance' => 'floral',
                'size' => 'medium',
                'description' => 'A nice product',
                'presentation' => 'box',
            ]
            );
        $productMock->method('getAttributes')->willReturn($attributesMock);

        $result = $this->presenter->presentCollection([$productMock, $productMock]);

        $this->assertCount(2, $result);
        $this->assertEquals('p1', $result[0]['id']);
        $this->assertEquals('p1', $result[1]['id']);
    }

    public function testPresentThrowsExceptionWhenCategoryIsNull(): void
    {
        $productMock = $this->createMock(Product::class);
        $productMock->method('getCategory')->willThrowException(new \Error('Call to a member function getId() on null'));

        $this->expectException(\Error::class);
        
        $this->presenter->present($productMock);
    }
}