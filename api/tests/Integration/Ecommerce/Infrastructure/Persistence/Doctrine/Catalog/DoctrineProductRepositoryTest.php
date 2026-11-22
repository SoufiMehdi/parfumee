<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Catalog\ProductCriteria;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductSortField;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Tests\Factory\Catalog\DoctrineCategoryFactory;
use App\Tests\Factory\Catalog\DoctrinePictureFactory;
use App\Tests\Factory\Catalog\DoctrineProductFactory;
use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

/**
 * Runs the repository against a real PostgreSQL database: the JSON filters and the
 * French sort of findPage() are native SQL, which a mocked EntityManager cannot check.
 */
final class DoctrineProductRepositoryTest extends DatabaseTestCase
{
    use ClockSensitiveTrait;

    private ProductRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(ProductRepositoryInterface::class);
    }

    public function testASavedProductIsReadBackWithEveryField(): void
    {
        $category = DoctrineCategoryFactory::createOne(['name' => 'Bougies']);
        $id = Uuid::v4()->toRfc4122();

        $this->repository->save(new Product(
            $id,
            'Bougie Ambre',
            'bougie-ambre',
            24.9,
            new Category($category->getId(), 'Bougies', $category->getSlug()),
            new Attribute(180.0, 'ambre', 'M', 'Cire de soja.', 'Verre ambré'),
        ));
        $this->entityManager->clear();

        $product = $this->repository->findById($id);

        self::assertInstanceOf(Product::class, $product);
        self::assertSame($id, $product->getId());
        self::assertSame('Bougie Ambre', $product->getName());
        self::assertSame('bougie-ambre', $product->getSlug());
        self::assertSame(24.9, $product->getPrice());
        self::assertSame($category->getId(), $product->getCategory()->getId());
        self::assertSame('Bougies', $product->getCategory()->getName());
        self::assertSame(
            ['weight' => 180.0, 'fragrance' => 'ambre', 'size' => 'M', 'description' => 'Cire de soja.', 'presentation' => 'Verre ambré'],
            $product->getAttributes()?->toArray(),
        );
        self::assertSame([], $product->getPictures());
        self::assertSame(ProductStatus::DRAFT, $product->getStatus());
    }

    public function testAProductWithoutAttributesIsReadBackWithNullAttributes(): void
    {
        $id = DoctrineProductFactory::createOne(['attributes' => []])->getId();
        $this->entityManager->clear();

        $product = $this->repository->findById($id);

        self::assertInstanceOf(Product::class, $product);
        self::assertSame(
            ['weight' => null, 'fragrance' => null, 'size' => null, 'description' => null, 'presentation' => null],
            $product->getAttributes()?->toArray(),
        );
    }

    public function testSavingAnExistingProductUpdatesItInsteadOfCreatingASecondOne(): void
    {
        $id = DoctrineProductFactory::createOne(['name' => 'Bougie Ambre', 'slug' => 'bougie-ambre', 'price' => 20.0])->getId();
        $otherCategory = DoctrineCategoryFactory::createOne(['name' => 'Fondants']);
        $this->entityManager->clear();

        $product = $this->repository->findById($id);
        \assert($product instanceof Product);
        $product->updateDetails(
            'Fondant Ambre',
            'fondant-ambre',
            9.5,
            new Category($otherCategory->getId(), 'Fondants', $otherCategory->getSlug()),
            new Attribute(50.0, 'ambre'),
        );
        $product->archive();
        $this->repository->save($product);
        $this->entityManager->clear();

        $reloaded = $this->repository->findById($id);
        \assert($reloaded instanceof Product);
        self::assertSame('Fondant Ambre', $reloaded->getName());
        self::assertSame('fondant-ambre', $reloaded->getSlug());
        self::assertSame(9.5, $reloaded->getPrice());
        self::assertSame($otherCategory->getId(), $reloaded->getCategory()->getId());
        self::assertSame(50.0, $reloaded->getAttributes()?->getWeight());
        self::assertSame(ProductStatus::ARCHIVED, $reloaded->getStatus());
        self::assertCount(1, $this->repository->findAll());
    }

    public function testAProductIsReadBackWithTheDatesOfItsSaves(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-11-01T09:30:00+00:00'));
        $category = DoctrineCategoryFactory::createOne();
        $id = Uuid::v4()->toRfc4122();
        $this->repository->save(new Product($id, 'Ambre', 'ambre', 20.0, new Category($category->getId(), $category->getName(), $category->getSlug())));
        $this->entityManager->clear();

        self::mockTime(new \DateTimeImmutable('2026-11-21T10:00:00+00:00'));
        $product = $this->repository->findById($id);
        \assert($product instanceof Product);
        $product->updateDetails('Ambre doux', 'ambre-doux', 20.0, $product->getCategory(), $product->getAttributes());
        $this->repository->save($product);

        // Read again right after the save, in the same request: what UpdateProductUseCase returns.
        $stored = $this->repository->findById($id);
        \assert($stored instanceof Product);
        self::assertEquals(new \DateTimeImmutable('2026-11-01T09:30:00+00:00'), $stored->getCreatedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-21T10:00:00+00:00'), $stored->getUpdatedAt());
        // The object that was saved keeps the dates it was loaded with.
        self::assertEquals(new \DateTimeImmutable('2026-11-01T09:30:00+00:00'), $product->getUpdatedAt());
    }

    public function testSavingAProductWithoutAnyChangeWritesNothing(): void
    {
        // A round price on purpose: PostgreSQL returns "20.00", and the save sets the
        // price again from the model (20.0): both must read the same to Doctrine.
        $id = DoctrineProductFactory::createOne(['price' => 20.0])->getId();
        $this->entityManager->clear();
        $product = $this->repository->findById($id);
        \assert($product instanceof Product);

        $queries = $this->queriesRunBy(fn () => $this->repository->save($product));

        // An UPDATE would move updated_at: of the product, and of its category.
        self::assertSame([], $queries, implode("\n", $queries));
    }

    public function testFindByIdReturnsNullWhenTheProductDoesNotExist(): void
    {
        self::assertNull($this->repository->findById(Uuid::v4()->toRfc4122()));
    }

    public function testFindAllReturnsDomainProducts(): void
    {
        DoctrineProductFactory::createMany(3);
        $this->entityManager->clear();

        $products = $this->repository->findAll();

        // The classes are compared at runtime: the PHPDoc alone would not catch a Doctrine entity (bug B1).
        self::assertSame(array_fill(0, 3, Product::class), array_map(static fn (object $item): string => $item::class, $products));
    }

    public function testAProductIsReadBackWithItsPictures(): void
    {
        $product = DoctrineProductFactory::createOne();
        DoctrinePictureFactory::createOne(['product' => $product, 'url' => 'products/a.jpg']);
        DoctrinePictureFactory::createOne(['product' => $product, 'url' => 'products/b.jpg']);
        DoctrinePictureFactory::createOne(['url' => 'products/other.jpg']);
        $id = $product->getId();
        $this->entityManager->clear();

        $reloaded = $this->repository->findById($id);
        \assert($reloaded instanceof Product);

        $urls = array_map(static fn ($picture): string => $picture->url(), $reloaded->getPictures());
        sort($urls);
        self::assertSame(['products/a.jpg', 'products/b.jpg'], $urls);
    }

    /**
     * The categories are named, not given by id: a data provider runs before the
     * test creates them. CANDLES and MELTS are replaced by their ids.
     *
     * @return iterable<string, array{array<string, mixed>, list<string>}>
     */
    public static function filters(): iterable
    {
        yield 'no filter returns everything' => [[], ['amber-m', 'amber-l', 'vanilla-m', 'melt']];
        yield 'category' => [['categoryId' => 'MELTS'], ['melt']];
        yield 'minimum price is inclusive' => [['minPrice' => 30.0], ['amber-l', 'vanilla-m']];
        yield 'maximum price is inclusive' => [['maxPrice' => 20.0], ['amber-m', 'melt']];
        yield 'price range' => [['minPrice' => 15.0, 'maxPrice' => 30.0], ['amber-m', 'amber-l']];
        yield 'fragrance' => [['fragrance' => 'amber'], ['amber-m', 'amber-l', 'melt']];
        yield 'size' => [['size' => 'M'], ['amber-m', 'vanilla-m']];
        yield 'every filter at once' => [['categoryId' => 'CANDLES', 'minPrice' => 10.0, 'maxPrice' => 25.0, 'fragrance' => 'amber', 'size' => 'M'], ['amber-m']];
        yield 'nothing matches' => [['fragrance' => 'rose'], []];
        yield 'the fragrance is matched exactly, not as a pattern' => [['fragrance' => 'amb%'], []];
    }

    /**
     * @param array<string, mixed> $filters
     * @param list<string>         $expectedSlugs
     */
    #[DataProvider('filters')]
    public function testFindPageFilters(array $filters, array $expectedSlugs): void
    {
        $candles = DoctrineCategoryFactory::createOne();
        $melts = DoctrineCategoryFactory::createOne();
        $this->givenAProduct('amber-m', 20.0, $candles, ['fragrance' => 'amber', 'size' => 'M']);
        $this->givenAProduct('amber-l', 30.0, $candles, ['fragrance' => 'amber', 'size' => 'L']);
        $this->givenAProduct('vanilla-m', 45.0, $candles, ['fragrance' => 'vanilla', 'size' => 'M']);
        $this->givenAProduct('melt', 8.0, $melts, ['fragrance' => 'amber']);
        $this->entityManager->clear();

        if (isset($filters['categoryId'])) {
            $filters['categoryId'] = 'CANDLES' === $filters['categoryId'] ? $candles->getId() : $melts->getId();
        }

        $page = $this->repository->findPage(new ProductCriteria(...$filters), new Pagination());

        $slugs = $this->slugsOf($page);
        sort($slugs);
        sort($expectedSlugs);
        self::assertSame($expectedSlugs, $slugs);
        self::assertSame(\count($expectedSlugs), $page->total);
    }

    /**
     * @return iterable<string, array{list<ProductStatus>|null, list<string>}>
     */
    public static function statuses(): iterable
    {
        yield 'unsaid: the published ones, as the shop sees them' => [null, ['published']];
        yield 'drafts' => [[ProductStatus::DRAFT], ['draft']];
        yield 'archived' => [[ProductStatus::ARCHIVED], ['archived']];
        yield 'drafts and published, as the back-office lists them' => [ProductStatus::managed(), ['draft', 'published']];
        yield 'every status' => [ProductStatus::cases(), ['archived', 'draft', 'published']];
    }

    /**
     * @param list<ProductStatus>|null $statuses
     * @param list<string>             $expectedSlugs
     */
    #[DataProvider('statuses')]
    public function testFindPageKeepsToTheStatusesAskedFor(?array $statuses, array $expectedSlugs): void
    {
        $category = DoctrineCategoryFactory::createOne();
        foreach (ProductStatus::cases() as $status) {
            $this->givenAProduct($status->value, 20.0, $category, ['fragrance' => 'amber'], $status);
        }
        $this->entityManager->clear();

        // With another filter: the status is one more condition, never a replacement.
        $criteria = null === $statuses
            ? new ProductCriteria(fragrance: 'amber')
            : new ProductCriteria(statuses: $statuses, fragrance: 'amber');
        $page = $this->repository->findPage($criteria, new Pagination());

        $slugs = $this->slugsOf($page);
        sort($slugs);
        self::assertSame($expectedSlugs, $slugs);
        self::assertSame(\count($expectedSlugs), $page->total);
    }

    public function testATopLevelCategoryGivesTheProductsOfItsChildrenToo(): void
    {
        $candles = DoctrineCategoryFactory::createOne();
        $scented = DoctrineCategoryFactory::createOne(['parent' => $candles]);
        $melts = DoctrineCategoryFactory::createOne();
        $this->givenAProduct('plain', 20.0, $candles, []);
        $this->givenAProduct('scented', 25.0, $scented, []);
        $this->givenAProduct('melt', 8.0, $melts, []);
        $this->entityManager->clear();

        $parentPage = $this->repository->findPage(new ProductCriteria(categoryId: $candles->getId()), new Pagination());
        $childPage = $this->repository->findPage(new ProductCriteria(categoryId: $scented->getId()), new Pagination());

        $parentSlugs = $this->slugsOf($parentPage);
        sort($parentSlugs);
        self::assertSame(['plain', 'scented'], $parentSlugs);
        self::assertSame(2, $parentPage->total);
        // A child category gives its own products only.
        self::assertSame(['scented'], $this->slugsOf($childPage));
    }

    public function testFindPageReturnsOneSliceAndTheTotalOfTheWholeList(): void
    {
        $category = DoctrineCategoryFactory::createOne();
        foreach (['a', 'b', 'c', 'd', 'e'] as $name) {
            DoctrineProductFactory::createOne(['name' => $name, 'slug' => $name, 'category' => $category]);
        }
        $this->entityManager->clear();

        $pages = [];
        foreach ([1, 2, 3, 4] as $number) {
            $pages[$number] = $this->repository->findPage(new ProductCriteria(), new Pagination(page: $number, perPage: 2));
        }

        self::assertSame(['a', 'b'], $this->slugsOf($pages[1]));
        self::assertSame(['c', 'd'], $this->slugsOf($pages[2]));
        self::assertSame(['e'], $this->slugsOf($pages[3]));
        // After the last page: empty, not an error, and the total is still right.
        self::assertSame([], $this->slugsOf($pages[4]));
        foreach ($pages as $page) {
            self::assertSame(5, $page->total);
            self::assertSame(3, $page->totalPages());
        }
    }

    public function testProductsWithTheSameSortValueNeverShowOnTwoPages(): void
    {
        $category = DoctrineCategoryFactory::createOne();
        $ids = [];
        foreach (range(1, 7) as $i) {
            $ids[] = DoctrineProductFactory::createOne(['price' => 10.0, 'category' => $category])->getId();
        }
        $this->entityManager->clear();

        $seen = [];
        foreach ([1, 2, 3] as $number) {
            $page = $this->repository->findPage(new ProductCriteria(sortBy: ProductSortField::PRICE), new Pagination(page: $number, perPage: 3));
            foreach ($page->items as $product) {
                $seen[] = $product->getId();
            }
        }

        // Every product exactly once, in the order of the ids, which break the tie.
        sort($ids);
        self::assertSame($ids, $seen);
    }

    public function testNamesAreSortedAsAFrenchReaderExpects(): void
    {
        $category = DoctrineCategoryFactory::createOne();
        // The default collation of the database compares bytes: it would put the
        // capitals first and the accented letters last.
        foreach (['Zèbre', 'ambre', 'Été indien', 'eau', 'Œillet', 'Bougie'] as $name) {
            DoctrineProductFactory::createOne(['name' => $name, 'category' => $category]);
        }
        $this->entityManager->clear();

        $ascending = $this->repository->findPage(new ProductCriteria(), new Pagination());
        $descending = $this->repository->findPage(new ProductCriteria(direction: SortDirection::DESC), new Pagination());

        $expected = ['ambre', 'Bougie', 'eau', 'Été indien', 'Œillet', 'Zèbre'];
        self::assertSame($expected, $this->namesOf($ascending));
        self::assertSame(array_reverse($expected), $this->namesOf($descending));
    }

    public function testProductsCanBeSortedByPrice(): void
    {
        $category = DoctrineCategoryFactory::createOne();
        foreach (['cheap' => 8.0, 'dear' => 45.0, 'mid' => 20.0, 'upper' => 30.0] as $slug => $price) {
            DoctrineProductFactory::createOne(['slug' => $slug, 'price' => $price, 'category' => $category]);
        }
        $this->entityManager->clear();

        $ascending = $this->repository->findPage(new ProductCriteria(sortBy: ProductSortField::PRICE), new Pagination());
        $descending = $this->repository->findPage(new ProductCriteria(sortBy: ProductSortField::PRICE, direction: SortDirection::DESC), new Pagination());

        self::assertSame(['cheap', 'mid', 'upper', 'dear'], $this->slugsOf($ascending));
        self::assertSame(['dear', 'upper', 'mid', 'cheap'], $this->slugsOf($descending));
    }

    public function testProductsCanBeSortedByCreationDate(): void
    {
        $category = DoctrineCategoryFactory::createOne();
        foreach (['first' => '2026-11-01', 'third' => '2026-11-03', 'second' => '2026-11-02'] as $slug => $day) {
            self::mockTime(new \DateTimeImmutable($day.'T09:30:00+00:00'));
            DoctrineProductFactory::createOne(['slug' => $slug, 'category' => $category]);
        }
        $this->entityManager->clear();

        $oldestFirst = $this->repository->findPage(new ProductCriteria(sortBy: ProductSortField::CREATED_AT), new Pagination());
        $newestFirst = $this->repository->findPage(new ProductCriteria(sortBy: ProductSortField::CREATED_AT, direction: SortDirection::DESC), new Pagination());

        self::assertSame(['first', 'second', 'third'], $this->slugsOf($oldestFirst));
        self::assertSame(['third', 'second', 'first'], $this->slugsOf($newestFirst));
    }

    public function testAPageIsLoadedWithCategoriesAndPicturesInThreeQueries(): void
    {
        foreach (range(1, 5) as $i) {
            // Child categories: their parent comes in the same query.
            $parent = DoctrineCategoryFactory::createOne(['name' => 'Parent '.$i]);
            $product = DoctrineProductFactory::createOne(['category' => DoctrineCategoryFactory::createOne(['name' => 'Category '.$i, 'parent' => $parent])]);
            DoctrinePictureFactory::createMany(2, ['product' => $product]);
        }
        $this->entityManager->clear();

        $page = null;
        $queries = $this->queriesRunBy(function () use (&$page): void {
            $page = $this->repository->findPage(new ProductCriteria(), new Pagination());
        });

        // The total, the ids of the page, then the products with their relations:
        // not one more query per product, however many there are.
        self::assertCount(3, $queries, implode("\n", $queries));
        self::assertInstanceOf(Page::class, $page);
        self::assertCount(5, $page->items);
        foreach ($page->items as $product) {
            self::assertStringStartsWith('Category ', $product->getCategory()->getName());
            self::assertStringStartsWith('Parent ', (string) $product->getCategory()->getParent()?->getName());
            self::assertCount(2, $product->getPictures());
        }
    }

    /**
     * @param Page<Product> $page
     *
     * @return list<string>
     */
    private function slugsOf(Page $page): array
    {
        return array_map(static fn (Product $product): string => $product->getSlug(), $page->items);
    }

    /**
     * @param Page<Product> $page
     *
     * @return list<string>
     */
    private function namesOf(Page $page): array
    {
        return array_map(static fn (Product $product): string => $product->getName(), $page->items);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function givenAProduct(string $slug, float $price, DoctrineCategory $category, array $attributes, ProductStatus $status = ProductStatus::PUBLISHED): void
    {
        DoctrineProductFactory::createOne([
            'slug' => $slug,
            'price' => $price,
            'category' => $category,
            'attributes' => $attributes,
            'status' => $status,
        ]);
    }
}
