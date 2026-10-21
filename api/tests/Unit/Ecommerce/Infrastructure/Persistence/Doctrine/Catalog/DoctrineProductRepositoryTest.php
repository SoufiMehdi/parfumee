<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog\DoctrineProductRepository;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\ProductMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NativeQuery;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DoctrineProductRepositoryTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private ProductMapper|MockObject $mapper;
    private DoctrineProductRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mapper = $this->createMock(ProductMapper::class);
        $this->repository = new DoctrineProductRepository($this->entityManager, $this->mapper);
    }

    public function testSaveNewProduct(): void
    {
        $product = $this->createMock(Product::class);
        $category = $this->createMock(Category::class);
        $doctrineCategory = new DoctrineCategory(
            'cat-1',
            'Category 1',
            'slug-cat-1'
        );
        $doctrineProduct = new DoctrineProduct(
            'prod-1',
            'Product 1',
            100.0,
            $doctrineCategory,
            [
                'description' => 'Description of Product 1',
                'size' => 'M'
            ]
        );

        $product->method('getId')->willReturn('prod-1');
        $product->method('getCategory')->willReturn($category);
        $category->method('getId')->willReturn('cat-1');

        $this->entityManager->expects($this->once())
            ->method('find')
            ->with(DoctrineProduct::class, 'prod-1')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('getReference')
            ->with(DoctrineCategory::class, 'cat-1')
            ->willReturn($doctrineCategory);

        $this->mapper->expects($this->once())
            ->method('toInfrastructure')
            ->with($product, $doctrineCategory)
            ->willReturn($doctrineProduct);

        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($doctrineProduct);
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($product);
    }

    public function testFindByIdReturnsProduct(): void
    {
        $productId = 'prod-1';
        $doctrineProduct = new DoctrineProduct(
            $productId,
            'Product 1',
            100.0,
            new DoctrineCategory('cat-1', 'Category 1', 'slug-cat-1'),
            [
                'description' => 'Description of Product 1',
                'size' => 'M'

            ]
        );
        $product = $this->createMock(Product::class);
        $repository = $this->createMock(EntityRepository::class);

        $this->entityManager->method('getRepository')
            ->with(DoctrineProduct::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('find')
            ->with($productId)
            ->willReturn($doctrineProduct);

        $this->mapper->expects($this->once())
            ->method('toDomain')
            ->with($doctrineProduct)
            ->willReturn($product);

        $result = $this->repository->findById($productId);

        $this->assertSame($product, $result);
    }
     public function testFindAllReturnsCollectionOfProducts(): void
    {
        $doctrineProduct1 = $this->createMock(DoctrineProduct::class);
        $doctrineProduct2 = $this->createMock(DoctrineProduct::class);
        $product1 = $this->createMock(Product::class);
        $product2 = $this->createMock(Product::class);
        
        $repository = $this->createMock(EntityRepository::class);

        $this->entityManager->method('getRepository')
            ->with(DoctrineProduct::class)
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('findAll')
            ->willReturn([$doctrineProduct1, $doctrineProduct2]);

        $this->mapper->expects($this->exactly(2))
            ->method('toDomain')
            ->willReturnMap([
                [$doctrineProduct1, $product1],
                [$doctrineProduct2, $product2],
            ]);

        $result = $this->repository->findAll();

        $this->assertCount(2, $result);
        $this->assertSame([$product1, $product2], $result);
    }

    public function testFindByFilterReturnsFilteredProducts(): void
    {
        $filterDto = new GetProductsFilterDto(
            categoryId: 'cat-1',
            minPrice: 50.0,
            maxPrice: 150.0,
            scent: 'lavender',
            size: 'M'
        );

        $doctrineProduct = $this->createMock(DoctrineProduct::class);
        $product = $this->createMock(Product::class);
        $query = $this->createMock(NativeQuery::class);

        $this->entityManager->expects($this->once())
            ->method('createNativeQuery')
            ->willReturn($query);

        $query->expects($this->exactly(5))
            ->method('setParameter');

        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$doctrineProduct]);

        $this->mapper->expects($this->once())
            ->method('toDomain')
            ->with($doctrineProduct)
            ->willReturn($product);

        $result = $this->repository->findByFilter($filterDto);

        $this->assertSame([$product], $result);
    }
}

