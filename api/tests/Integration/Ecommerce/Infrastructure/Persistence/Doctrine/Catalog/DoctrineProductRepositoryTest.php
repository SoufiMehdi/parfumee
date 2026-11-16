<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Tests\Factory\Catalog\DoctrineCategoryFactory;
use App\Tests\Factory\Catalog\DoctrinePictureFactory;
use App\Tests\Factory\Catalog\DoctrineProductFactory;
use App\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Uid\Uuid;

/**
 * Runs the repository against a real PostgreSQL database: the JSON filters of
 * findByFilter() are native SQL, which a mocked EntityManager cannot check.
 */
final class DoctrineProductRepositoryTest extends DatabaseTestCase
{
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
        $this->repository->save($product);
        $this->entityManager->clear();

        $reloaded = $this->repository->findById($id);
        \assert($reloaded instanceof Product);
        self::assertSame('Fondant Ambre', $reloaded->getName());
        self::assertSame('fondant-ambre', $reloaded->getSlug());
        self::assertSame(9.5, $reloaded->getPrice());
        self::assertSame($otherCategory->getId(), $reloaded->getCategory()->getId());
        self::assertSame(50.0, $reloaded->getAttributes()?->getWeight());
        self::assertCount(1, $this->repository->findAll());
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
     * @return iterable<string, array{GetProductsFilterDto, list<string>}>
     */
    public static function filters(): iterable
    {
        yield 'no filter returns everything' => [new GetProductsFilterDto(), ['amber-m', 'amber-l', 'vanilla-m', 'melt']];
        yield 'category' => [new GetProductsFilterDto(categoryId: 'MELTS'), ['melt']];
        yield 'minimum price is inclusive' => [new GetProductsFilterDto(minPrice: 30.0), ['amber-l', 'vanilla-m']];
        yield 'maximum price is inclusive' => [new GetProductsFilterDto(maxPrice: 20.0), ['amber-m', 'melt']];
        yield 'price range' => [new GetProductsFilterDto(minPrice: 15.0, maxPrice: 30.0), ['amber-m', 'amber-l']];
        yield 'scent' => [new GetProductsFilterDto(scent: 'amber'), ['amber-m', 'amber-l', 'melt']];
        yield 'size' => [new GetProductsFilterDto(size: 'M'), ['amber-m', 'vanilla-m']];
        yield 'every filter at once' => [new GetProductsFilterDto('CANDLES', 10.0, 25.0, 'amber', 'M'), ['amber-m']];
        yield 'nothing matches' => [new GetProductsFilterDto(scent: 'rose'), []];
        yield 'the scent is matched exactly, not as a pattern' => [new GetProductsFilterDto(scent: 'amb%'), []];
    }

    /**
     * @param list<string> $expectedSlugs
     */
    #[DataProvider('filters')]
    public function testFindByFilter(GetProductsFilterDto $filter, array $expectedSlugs): void
    {
        $candles = DoctrineCategoryFactory::createOne();
        $melts = DoctrineCategoryFactory::createOne();
        $this->givenAProduct('amber-m', 20.0, $candles, ['fragrance' => 'amber', 'size' => 'M']);
        $this->givenAProduct('amber-l', 30.0, $candles, ['fragrance' => 'amber', 'size' => 'L']);
        $this->givenAProduct('vanilla-m', 45.0, $candles, ['fragrance' => 'vanilla', 'size' => 'M']);
        $this->givenAProduct('melt', 8.0, $melts, ['fragrance' => 'amber']);
        $this->entityManager->clear();

        // The provider is static and cannot know the generated ids: it names the category instead.
        $filter->categoryId = match ($filter->categoryId) {
            'CANDLES' => $candles->getId(),
            'MELTS' => $melts->getId(),
            default => $filter->categoryId,
        };

        $products = $this->repository->findByFilter($filter);

        $slugs = array_map(static fn (Product $product): string => $product->getSlug(), $products);
        sort($slugs);
        sort($expectedSlugs);
        self::assertSame($expectedSlugs, $slugs);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function givenAProduct(string $slug, float $price, DoctrineCategory $category, array $attributes): void
    {
        DoctrineProductFactory::createOne([
            'slug' => $slug,
            'price' => $price,
            'category' => $category,
            'attributes' => $attributes,
        ]);
    }
}
