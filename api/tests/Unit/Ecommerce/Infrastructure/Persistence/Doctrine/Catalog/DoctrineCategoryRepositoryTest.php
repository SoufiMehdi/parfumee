<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog\DoctrineCategoryRepository;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\CategoryMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DoctrineCategoryRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CategoryMapper&MockObject $mapper;
    private DoctrineCategoryRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mapper = $this->createMock(CategoryMapper::class);
        $this->repository = new DoctrineCategoryRepository($this->entityManager, $this->mapper);
    }

    public function testSavePersistsAndFlushesCategory(): void
    {
        $category = $this->createMock(Category::class);
        $doctrineCategory = new DoctrineCategory(
            'cat-123',
            'Test Category',
            'test-category'
        );

        $this->mapper->expects($this->once())
            ->method('toInfrastructure')
            ->with($category)
            ->willReturn($doctrineCategory);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($doctrineCategory);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->repository->save($category);
    }

    public function testFindByIdReturnsCategory(): void
    {
        $id = 'cat-123';
        $doctrineCategory = new DoctrineCategory(
            $id,
            'Test Category',
            'test-category'
        );
        $category = $this->createMock(Category::class);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('find')->with($id)->willReturn($doctrineCategory);

        $this->entityManager->method('getRepository')->willReturn($entityRepository);
        $this->mapper->method('toDomain')->with($doctrineCategory)->willReturn($category);

        $result = $this->repository->findById($id);

        $this->assertSame($category, $result);
    }

    public function testFindByIdReturnsNullIfNotFound(): void
    {
        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('find')->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($entityRepository);

        $this->assertNull($this->repository->findById('non-existent'));
    }

    public function testFindBySlugReturnsCategory(): void
    {
        $slug = 'test-category';
        $doctrineCategory = new DoctrineCategory(
            'cat-123',
            'Test Category',
            $slug
        );
        $category = $this->createMock(Category::class);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('findOneBy')->with(['slug' => $slug])->willReturn($doctrineCategory);

        $this->entityManager->method('getRepository')->willReturn($entityRepository);
        $this->mapper->method('toDomain')->with($doctrineCategory)->willReturn($category);

        $result = $this->repository->findBySlug($slug);

        $this->assertSame($category, $result);
    }

    public function testFindAllReturnsArrayOfCategories(): void
    {
        $doctrineCategory = new DoctrineCategory(
            'cat-123',
            'Test Category',
            'test-category'
        );
        $category = $this->createMock(Category::class);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('findAll')->willReturn([$doctrineCategory]);

        $this->entityManager->method('getRepository')->willReturn($entityRepository);
        $this->mapper->method('toDomain')->willReturn($category);

        $result = $this->repository->findAll();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertSame($category, $result[0]);
    }

    public function testSaveThrowsExceptionOnDatabaseError(): void
    {
        $category = $this->createMock(Category::class);
        
        $this->mapper->method('toInfrastructure')->willReturn(new DoctrineCategory(
            'cat-123',
            'Test Category',
            'test-category'
        ));
        $this->entityManager->method('persist')->willThrowException(new \RuntimeException('DB Error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB Error');

        $this->repository->save($category);
    }
}