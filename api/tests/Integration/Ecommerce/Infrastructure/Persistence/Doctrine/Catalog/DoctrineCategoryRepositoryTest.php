<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Tests\Factory\Catalog\DoctrineCategoryFactory;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final class DoctrineCategoryRepositoryTest extends DatabaseTestCase
{
    private CategoryRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(CategoryRepositoryInterface::class);
    }

    public function testASavedCategoryIsReadBackById(): void
    {
        $id = Uuid::v4()->toRfc4122();

        $this->repository->save(new Category($id, 'Bougies parfumées', 'bougies-parfumees'));
        $this->entityManager->clear();

        $category = $this->repository->findById($id);

        self::assertInstanceOf(Category::class, $category);
        self::assertSame($id, $category->getId());
        self::assertSame('Bougies parfumées', $category->getName());
        self::assertSame('bougies-parfumees', $category->getSlug());
    }

    public function testFindByIdReturnsNullWhenTheCategoryDoesNotExist(): void
    {
        self::assertNull($this->repository->findById(Uuid::v4()->toRfc4122()));
    }

    public function testFindBySlug(): void
    {
        DoctrineCategoryFactory::createOne(['name' => 'Fondants', 'slug' => 'fondants']);
        DoctrineCategoryFactory::createOne(['slug' => 'bougies']);
        $this->entityManager->clear();

        $category = $this->repository->findBySlug('fondants');

        self::assertInstanceOf(Category::class, $category);
        self::assertSame('Fondants', $category->getName());
        self::assertNull($this->repository->findBySlug('unknown'));
    }

    public function testFindAllReturnsDomainCategories(): void
    {
        DoctrineCategoryFactory::createMany(3);
        $this->entityManager->clear();

        $categories = $this->repository->findAll();

        // The classes are compared at runtime: the PHPDoc alone would not catch a Doctrine entity (bug B1).
        self::assertSame(array_fill(0, 3, Category::class), array_map(static fn (object $item): string => $item::class, $categories));
    }

    public function testTwoCategoriesCannotShareASlug(): void
    {
        DoctrineCategoryFactory::createOne(['slug' => 'bougies']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repository->save(new Category(Uuid::v4()->toRfc4122(), 'Autres bougies', 'bougies'));
    }
}
