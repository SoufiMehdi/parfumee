<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\PictureRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Runs the repository against a real PostgreSQL database: the unit test mocks
 * the EntityManager, which is how B1 (querying the domain model instead of
 * the Doctrine entity) went unnoticed.
 *
 * Needs a migrated test database:
 *   php bin/console doctrine:database:create --env=test --if-not-exists
 *   php bin/console doctrine:migrations:migrate --env=test -n
 *
 * Skipped when no database server answers, until P0-10 makes the test
 * database part of the standard setup. Each test runs in a transaction that
 * is rolled back.
 */
final class DoctrinePictureRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private PictureRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(PictureRepositoryInterface::class);

        try {
            $this->entityManager->getConnection()->beginTransaction();
        } catch (ConnectionException $e) {
            self::markTestSkipped('No test database available: '.$e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        if ($connection->isTransactionActive()) {
            $connection->rollBack();
        }

        parent::tearDown();
    }

    public function testASavedPictureIsReadBackAsADomainPicture(): void
    {
        $product = $this->givenAProduct();
        $id = Uuid::v4()->toRfc4122();

        $this->repository->save(new Picture($id, 'products/candle.jpg', 'A candle', 2, $product));
        $this->entityManager->clear();

        $picture = $this->repository->findById($id);

        self::assertInstanceOf(Picture::class, $picture);
        self::assertSame($id, $picture->getId());
        self::assertSame('products/candle.jpg', $picture->url());
        self::assertSame('A candle', $picture->getAlt());
        self::assertSame(2, $picture->getSortOrder());
    }

    public function testFindByIdReturnsNullWhenThePictureDoesNotExist(): void
    {
        self::assertNull($this->repository->findById(Uuid::v4()->toRfc4122()));
    }

    public function testFindByProductIdOnlyReturnsThePicturesOfThatProduct(): void
    {
        $product = $this->givenAProduct();
        $otherProduct = $this->givenAProduct();
        $this->repository->save(new Picture(Uuid::v4()->toRfc4122(), 'products/a.jpg', product: $product));
        $this->repository->save(new Picture(Uuid::v4()->toRfc4122(), 'products/b.jpg', product: $product));
        $this->repository->save(new Picture(Uuid::v4()->toRfc4122(), 'products/other.jpg', product: $otherProduct));
        $this->entityManager->clear();

        $pictures = $this->repository->findByProductId($product->getId());

        $urls = array_map(static fn (Picture $picture): string => $picture->url(), $pictures);
        sort($urls);
        self::assertSame(['products/a.jpg', 'products/b.jpg'], $urls);
    }

    public function testFindAllReturnsDomainPictures(): void
    {
        $id = Uuid::v4()->toRfc4122();
        $this->repository->save(new Picture($id, 'products/a.jpg', product: $this->givenAProduct()));
        $this->entityManager->clear();

        $pictures = $this->repository->findAll();

        self::assertContains($id, array_map(static fn (Picture $picture): string => $picture->getId(), $pictures));
    }

    public function testAProductIsReadBackWithItsPictures(): void
    {
        $product = $this->givenAProduct();
        $this->repository->save(new Picture(Uuid::v4()->toRfc4122(), 'products/a.jpg', product: $product));
        $this->entityManager->clear();

        $reloaded = self::getContainer()->get(ProductRepositoryInterface::class)->findById($product->getId());

        self::assertInstanceOf(Product::class, $reloaded);
        self::assertCount(1, $reloaded->getPictures());
        self::assertSame('products/a.jpg', $reloaded->getPictures()[0]->url());
    }

    private function givenAProduct(): Product
    {
        $categoryId = Uuid::v4()->toRfc4122();
        $productId = Uuid::v4()->toRfc4122();

        $category = new DoctrineCategory($categoryId, 'Candles', 'candles-'.$categoryId);
        $this->entityManager->persist($category);
        // A weight is set on purpose: reading a product without one triggers B5 (fixed in P0-07).
        $this->entityManager->persist(new DoctrineProduct($productId, 'Amber candle', 24.9, $category, ['weight' => 180]));
        $this->entityManager->flush();

        $product = self::getContainer()->get(ProductRepositoryInterface::class)->findById($productId);
        \assert($product instanceof Product);

        return $product;
    }
}
