<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Mapper\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Application\Mapper\AttributesMapper;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\CategoryMapper;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\PictureMapper;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\ProductMapper;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductMapperTest extends TestCase
{
    private CategoryMapper|MockObject $categoryMapper;
    private AttributesMapper|MockObject $attributesMapper;
    private PictureMapper|MockObject $pictureMapper;
    private ProductMapper $mapper;

    protected function setUp(): void
    {
        $this->categoryMapper = $this->createMock(CategoryMapper::class);
        $this->attributesMapper = $this->createMock(AttributesMapper::class);
        $this->pictureMapper = $this->createMock(PictureMapper::class);

        $this->mapper = new ProductMapper(
            $this->categoryMapper,
            $this->attributesMapper,
            $this->pictureMapper
        );
    }

    public function testToInfrastructure(): void
    {
        // 1. Arrange
        $product = $this->createMock(Product::class);
        $doctrineCategory = $this->createMock(DoctrineCategory::class);
        $attributeMock = $this->createMock(Attribute::class); // Création d'un mock d'Attribute pour respecter le type de retour

        $product->method('getId')->willReturn('id-1');
        $product->method('getName')->willReturn('Product Name');
        $product->method('getPrice')->willReturn(100.50);
        $product->method('getAttributes')->willReturn($attributeMock); // Retourne le mock au lieu d'un tableau array

        $this->attributesMapper->expects($this->once())
            ->method('toDto')
            ->with($attributeMock)
            ->willReturn(['color' => 'red']); // Le mapper d'infrastructure, lui, convertit bien en tableau pour Doctrine

        // 2. Act
        $result = $this->mapper->toInfrastructure($product, $doctrineCategory);

        // 3. Assert
        $this->assertInstanceOf(DoctrineProduct::class, $result);
        $this->assertSame('id-1', $result->getId());
        $this->assertSame('Product Name', $result->getName());
        $this->assertSame(100.50, $result->getPrice());
        $this->assertSame($doctrineCategory, $result->getCategory());
    }

    public function testMapDomainToEntity(): void
    {
        // 1. Arrange
        $product = $this->createMock(Product::class);
        $doctrineProduct = $this->createMock(DoctrineProduct::class);
        $doctrineCategory = $this->createMock(DoctrineCategory::class);
        $attributeMock = $this->createMock(Attribute::class);

        $product->method('getName')->willReturn('Updated Name');
        $product->method('getPrice')->willReturn(200.00);
        $product->method('getAttributes')->willReturn($attributeMock);

        $this->attributesMapper->expects($this->once())
            ->method('toDto')
            ->with($attributeMock)
            ->willReturn(['size' => 'L']);

        // Assertions sur l'objet de base de données qui va recevoir les modifications
        $doctrineProduct->expects($this->once())->method('setName')->with('Updated Name');
        $doctrineProduct->expects($this->once())->method('setPrice')->with(200.00);
        $doctrineProduct->expects($this->once())->method('setCategory')->with($doctrineCategory);
        $doctrineProduct->expects($this->once())->method('setAttributes')->with(['size' => 'L']);

        // 2. Act
        $this->mapper->mapDomainToEntity($product, $doctrineProduct, $doctrineCategory);
    }

    public function testToDomain(): void
    {
        // 1. Arrange
        $doctrineProduct = $this->createMock(DoctrineProduct::class);
        $doctrineCategory = $this->createMock(DoctrineCategory::class);
        $doctrinePicture = $this->createMock(DoctrinePicture::class);
        $category = $this->createMock(Category::class);
        $attribute = $this->createMock(Attribute::class);

        $doctrineProduct->method('getId')->willReturn('id-1');
        $doctrineProduct->method('getName')->willReturn('Name');
        $doctrineProduct->method('getPrice')->willReturn(10.0);
        $doctrineProduct->method('getCategory')->willReturn($doctrineCategory);
        $doctrineProduct->method('getAttributes')->willReturn(['color' => 'blue']); // Doctrine renvoie un tableau
        $doctrineProduct->method('getPictures')->willReturn(new ArrayCollection([$doctrinePicture]));

        $this->categoryMapper->expects($this->once())->method('toDomain')->with($doctrineCategory)->willReturn($category);
        $this->attributesMapper->expects($this->once())->method('fromArray')->with(['color' => 'blue'])->willReturn($attribute);
        $this->pictureMapper->expects($this->once())->method('toDomain')->with($doctrinePicture);

        // 2. Act
        $result = $this->mapper->toDomain($doctrineProduct);

        // 3. Assert
        $this->assertInstanceOf(Product::class, $result);
    }
}
