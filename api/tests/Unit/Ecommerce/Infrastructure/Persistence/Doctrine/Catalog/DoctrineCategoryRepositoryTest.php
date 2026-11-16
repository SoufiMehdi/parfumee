<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog\DoctrineCategoryRepository;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\CategoryMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
        $category = $this->createStub(Category::class);
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

    #[AllowMockObjectsWithoutExpectations]
    public function testFindByIdReturnsCategory(): void
    {
        $id = 'cat-123';
        $doctrineCategory = new DoctrineCategory(
            $id,
            'Test Category',
            'test-category'
        );
        $category = $this->createStub(Category::class);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->once())->method('find')->with($id)->willReturn($doctrineCategory);

        $this->entityManager->method('getRepository')->willReturn($entityRepository);
        $this->mapper->expects($this->once())->method('toDomain')->with($doctrineCategory)->willReturn($category);

        $result = $this->repository->findById($id);

        $this->assertSame($category, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFindByIdReturnsNullIfNotFound(): void
    {
        $entityRepository = $this->createStub(EntityRepository::class);
        $entityRepository->method('find')->willReturn(null);

        $this->entityManager->method('getRepository')->willReturn($entityRepository);

        $this->assertNull($this->repository->findById('non-existent'));
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFindBySlugReturnsCategory(): void
    {
        $slug = 'test-category';
        $doctrineCategory = new DoctrineCategory(
            'cat-123',
            'Test Category',
            $slug
        );
        $category = $this->createStub(Category::class);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->expects($this->once())->method('findOneBy')->with(['slug' => $slug])->willReturn($doctrineCategory);

        $this->entityManager->method('getRepository')->willReturn($entityRepository);
        $this->mapper->expects($this->once())->method('toDomain')->with($doctrineCategory)->willReturn($category);

        $result = $this->repository->findBySlug($slug);

        $this->assertSame($category, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFindAllReturnsArrayOfCategories(): void
    {
        $doctrineCategory = new DoctrineCategory(
            'cat-123',
            'Test Category',
            'test-category'
        );
        $category = $this->createStub(Category::class);

        $entityRepository = $this->createStub(EntityRepository::class);
        $entityRepository->method('findAll')->willReturn([$doctrineCategory]);

        $this->entityManager->method('getRepository')->willReturn($entityRepository);
        $this->mapper->method('toDomain')->willReturn($category);

        $result = $this->repository->findAll();

        $this->assertCount(1, $result);
        $this->assertSame($category, $result[0]);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testSaveThrowsExceptionOnDatabaseError(): void
    {
        $category = $this->createStub(Category::class);

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
