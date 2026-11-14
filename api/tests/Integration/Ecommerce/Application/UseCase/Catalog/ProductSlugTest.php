<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\UseCase\Catalog\CreateProductUseCase;
use App\Ecommerce\Application\UseCase\Catalog\UpdateProductUseCase;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

/**
 * B4: the slug used to be computed at creation, never stored, and read back
 * as "slug-placeholder" for every product.
 */
final class ProductSlugTest extends DatabaseTestCase
{
    private string $categoryId;
    private ProductRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(ProductRepositoryInterface::class);
        $this->categoryId = Uuid::v4()->toRfc4122();
        $this->entityManager->persist(new DoctrineCategory($this->categoryId, 'Candles', 'candles-'.$this->categoryId));
        $this->entityManager->flush();
    }

    public function testTheSlugIsStoredAndReadBack(): void
    {
        $id = $this->create("Bougie Été à l'Ambre");

        self::assertSame('bougie-ete-a-l-ambre', $this->reload($id)->getSlug());
    }

    public function testTheSlugIsReadBackByEveryFinder(): void
    {
        $this->create('Amber candle');

        $slugs = static fn (array $products): array => array_map(static fn (Product $product): string => $product->getSlug(), $products);

        self::assertSame(['amber-candle'], $slugs($this->repository->findAll()));
    }

    public function testTwoProductsWithTheSameNameGetDifferentSlugs(): void
    {
        $first = $this->create('Amber candle');
        $second = $this->create('Amber candle');
        $third = $this->create('AMBER candle !');

        self::assertSame('amber-candle', $this->reload($first)->getSlug());
        self::assertSame('amber-candle-2', $this->reload($second)->getSlug());
        self::assertSame('amber-candle-3', $this->reload($third)->getSlug());
    }

    public function testRenamingAProductChangesItsSlug(): void
    {
        $id = $this->create('Amber candle');

        $this->update($id, 'Vanilla candle');

        self::assertSame('vanilla-candle', $this->reload($id)->getSlug());
    }

    public function testRenamingToATakenNameGetsASuffix(): void
    {
        $this->create('Vanilla candle');
        $id = $this->create('Amber candle');

        $this->update($id, 'Vanilla candle');

        self::assertSame('vanilla-candle-2', $this->reload($id)->getSlug());
    }

    public function testEditingWithoutRenamingKeepsTheSlug(): void
    {
        $this->create('Amber candle');
        $id = $this->create('Amber candle');

        $this->update($id, 'Amber candle', price: 30.0);

        $product = $this->reload($id);
        self::assertSame('amber-candle-2', $product->getSlug());
        self::assertSame(30.0, $product->getPrice());
    }

    public function testRenamingToANameWithTheSameSlugDoesNotCollideWithItself(): void
    {
        $id = $this->create('Amber candle');

        $this->update($id, 'AMBER candle');

        self::assertSame('amber-candle', $this->reload($id)->getSlug());
    }

    public function testSlugExists(): void
    {
        $id = $this->create('Amber candle');

        self::assertTrue($this->repository->slugExists('amber-candle'));
        self::assertFalse($this->repository->slugExists('amber-candle', $id));
        self::assertFalse($this->repository->slugExists('vanilla-candle'));
    }

    public function testTheDatabaseRefusesADuplicateSlug(): void
    {
        $category = $this->entityManager->getReference(DoctrineCategory::class, $this->categoryId);
        $this->entityManager->persist(new DoctrineProduct(Uuid::v4()->toRfc4122(), 'A', 'same-slug', 1.0, $category));
        $this->entityManager->persist(new DoctrineProduct(Uuid::v4()->toRfc4122(), 'B', 'same-slug', 1.0, $category));

        $this->expectException(UniqueConstraintViolationException::class);

        $this->entityManager->flush();
    }

    private function create(string $name): string
    {
        $id = self::getContainer()->get(CreateProductUseCase::class)->execute(
            new CreateProductDto(name: $name, price: 24.9, categoryId: $this->categoryId, attributes: []),
        );
        \assert(\is_string($id));

        return $id;
    }

    private function update(string $id, string $name, float $price = 24.9): void
    {
        self::getContainer()->get(UpdateProductUseCase::class)->execute(
            new UpdateProductDto(name: $name, price: $price, categoryId: $this->categoryId, attributes: []),
            $id,
        );
    }

    private function reload(string $id): Product
    {
        $this->entityManager->clear();
        $product = $this->repository->findById($id);
        \assert($product instanceof Product);

        return $product;
    }
}
