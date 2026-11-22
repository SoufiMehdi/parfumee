<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\CategoryNotEmptyException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Catalog\CategoryCriteria;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\CategorySortField;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use App\Tests\Factory\Catalog\DoctrineCategoryFactory;
use App\Tests\Factory\Catalog\DoctrineProductFactory;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class DoctrineCategoryRepositoryTest extends DatabaseTestCase
{
    use ClockSensitiveTrait;

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

    public function testAChildCategoryIsSavedAndReadBackWithItsParent(): void
    {
        $parent = DoctrineCategoryFactory::createOne(['name' => 'Bougies']);
        $id = Uuid::v4()->toRfc4122();

        $this->repository->save(new Category($id, 'Bougies parfumées', 'bougies-parfumees', new Category($parent->getId(), 'Bougies', $parent->getSlug())));
        $this->entityManager->clear();

        $category = $this->repository->findById($id);
        self::assertInstanceOf(Category::class, $category);
        self::assertSame($parent->getId(), $category->getParent()?->getId());
        self::assertSame('Bougies', $category->getParent()->getName());
        self::assertNotNull($category->getCreatedAt());
    }

    public function testSavingAnExistingCategoryUpdatesIt(): void
    {
        $parent = DoctrineCategoryFactory::createOne();
        $id = DoctrineCategoryFactory::createOne(['name' => 'Fondant', 'slug' => 'fondant', 'parent' => $parent])->getId();
        $this->entityManager->clear();

        $category = $this->repository->findById($id);
        \assert($category instanceof Category);
        $category->rename('Fondants', 'fondants');
        $category->moveUnder(null);
        $this->repository->save($category);
        $this->entityManager->clear();

        $reloaded = $this->repository->findById($id);
        \assert($reloaded instanceof Category);
        self::assertSame('Fondants', $reloaded->getName());
        self::assertSame('fondants', $reloaded->getSlug());
        self::assertTrue($reloaded->isTopLevel());
        self::assertCount(2, $this->repository->findAll());
    }

    public function testSavingACategoryWithoutAnyChangeWritesNothing(): void
    {
        $id = DoctrineCategoryFactory::createOne(['parent' => DoctrineCategoryFactory::createOne()])->getId();
        $this->entityManager->clear();
        $category = $this->repository->findById($id);
        \assert($category instanceof Category);

        $queries = $this->queriesRunBy(fn () => $this->repository->save($category));

        self::assertSame([], array_values(array_filter($queries, static fn (string $sql): bool => str_starts_with($sql, 'UPDATE'))), implode("\n", $queries));
    }

    public function testAnEmptyCategoryIsDeleted(): void
    {
        $id = DoctrineCategoryFactory::createOne()->getId();
        $this->entityManager->clear();
        $category = $this->repository->findById($id);
        \assert($category instanceof Category);

        $this->repository->delete($category);
        $this->entityManager->clear();

        self::assertNull($this->repository->findById($id));
    }

    public function testHasChildrenAndHasProducts(): void
    {
        $parent = DoctrineCategoryFactory::createOne();
        $child = DoctrineCategoryFactory::createOne(['parent' => $parent]);
        $empty = DoctrineCategoryFactory::createOne();
        // Archived: it still belongs to its category.
        DoctrineProductFactory::createOne(['category' => $child, 'status' => ProductStatus::ARCHIVED]);
        $this->entityManager->clear();

        self::assertTrue($this->repository->hasChildren($parent->getId()));
        self::assertFalse($this->repository->hasChildren($child->getId()));
        self::assertTrue($this->repository->hasProducts($child->getId()));
        self::assertFalse($this->repository->hasProducts($parent->getId()));
        self::assertFalse($this->repository->hasChildren($empty->getId()));
        self::assertFalse($this->repository->hasProducts($empty->getId()));
    }

    /**
     * The rule is checked again by a trigger: two requests at the same time could both pass
     * the checks of the use cases.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function rowsBreakingTheTwoLevels(): iterable
    {
        yield 'a third level' => ['other', 'child'];
        yield 'a category that holds others under another' => ['parent', 'other'];
        yield 'two categories under each other' => ['parent', 'child'];
    }

    /**
     * @param 'parent'|'child'|'other' $moved
     * @param 'parent'|'child'|'other' $newParent
     */
    #[DataProvider('rowsBreakingTheTwoLevels')]
    public function testTheDatabaseKeepsTheTwoLevels(string $moved, string $newParent): void
    {
        $parent = DoctrineCategoryFactory::createOne();
        $child = DoctrineCategoryFactory::createOne(['parent' => $parent]);
        $other = DoctrineCategoryFactory::createOne();

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);
        $this->expectExceptionMessageMatches('/child category cannot hold others|must stay at the top level/');

        $ids = ['parent' => $parent->getId(), 'child' => $child->getId(), 'other' => $other->getId()];

        $this->entityManager->getConnection()->executeStatement(
            'UPDATE categories SET parent_id = :newParent WHERE id = :moved',
            ['newParent' => $ids[$newParent], 'moved' => $ids[$moved]],
        );
    }

    public function testACategoryFiledInTheMeantimeKeepsItsParentWithA409(): void
    {
        $parent = DoctrineCategoryFactory::createOne();
        $id = $parent->getId();
        $this->entityManager->clear();
        $category = $this->repository->findById($id);
        \assert($category instanceof Category);
        // Filed after the use case checked: only the database sees it.
        DoctrineCategoryFactory::createOne(['parent' => DoctrineCategoryFactory::find($id)]);

        $this->expectException(CategoryNotEmptyException::class);

        $this->repository->delete($category);
    }

    public function testTheDatabaseRefusesACategoryThatIsItsOwnParent(): void
    {
        $id = DoctrineCategoryFactory::createOne()->getId();

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);

        $this->entityManager->getConnection()->executeStatement('UPDATE categories SET parent_id = id WHERE id = :id', ['id' => $id]);
    }

    /**
     * Named, not given by id: a data provider runs before the test creates them.
     *
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function filters(): iterable
    {
        yield 'no filter: every category, by name' => [[], ['Ambre', 'Bougies', 'Fondants', 'Vanille']];
        yield 'search, whatever the case' => [['search' => 'BOU'], ['Bougies']];
        yield 'search matches % as such' => [['search' => '%'], []];
        yield 'the children of a category' => [['parentId' => 'Bougies'], ['Ambre', 'Vanille']];
        yield 'the top-level categories' => [['topLevelOnly' => true], ['Bougies', 'Fondants']];
        yield 'newest first' => [['sortBy' => CategorySortField::CREATED_AT, 'direction' => SortDirection::DESC], ['Fondants', 'Vanille', 'Ambre', 'Bougies']];
    }

    /**
     * @param array<string, mixed> $criteria
     * @param list<string>         $expectedNames
     */
    #[DataProvider('filters')]
    public function testFindPage(array $criteria, array $expectedNames): void
    {
        self::mockTime(new \DateTimeImmutable('2026-11-22T10:00:00+00:00'));
        $candles = DoctrineCategoryFactory::createOne(['name' => 'Bougies']);
        self::mockTime(new \DateTimeImmutable('2026-11-22T10:01:00+00:00'));
        DoctrineCategoryFactory::createOne(['name' => 'Ambre', 'parent' => $candles]);
        self::mockTime(new \DateTimeImmutable('2026-11-22T10:02:00+00:00'));
        DoctrineCategoryFactory::createOne(['name' => 'Vanille', 'parent' => $candles]);
        self::mockTime(new \DateTimeImmutable('2026-11-22T10:03:00+00:00'));
        DoctrineCategoryFactory::createOne(['name' => 'Fondants']);
        $this->entityManager->clear();
        if (isset($criteria['parentId'])) {
            $criteria['parentId'] = $candles->getId();
        }

        $page = $this->repository->findPage(new CategoryCriteria(...$criteria), new Pagination());

        self::assertSame($expectedNames, array_map(static fn (Category $category): string => $category->getName(), $page->items));
        self::assertSame(\count($expectedNames), $page->total);
    }

    public function testAPageIsLoadedWithTheParentsInThreeQueries(): void
    {
        foreach (range(1, 4) as $i) {
            DoctrineCategoryFactory::createOne(['name' => 'Child '.$i, 'parent' => DoctrineCategoryFactory::createOne(['name' => 'Parent '.$i])]);
        }
        $this->entityManager->clear();

        $page = null;
        $queries = $this->queriesRunBy(function () use (&$page): void {
            // The children only: their parents are not on the page, they must come with them.
            $page = $this->repository->findPage(new CategoryCriteria(search: 'Child'), new Pagination());
        });

        self::assertCount(3, $queries, implode("\n", $queries));
        self::assertInstanceOf(Page::class, $page);
        self::assertCount(4, $page->items);
        foreach ($page->items as $category) {
            self::assertStringStartsWith('Parent ', (string) $category->getParent()?->getName());
        }
    }

    public function testTwoCategoriesCannotShareASlug(): void
    {
        DoctrineCategoryFactory::createOne(['slug' => 'bougies']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repository->save(new Category(Uuid::v4()->toRfc4122(), 'Autres bougies', 'bougies'));
    }
}
