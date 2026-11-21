<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\UseCase\Catalog\UploadProductImageUseCase;
use App\Ecommerce\Domain\Repository\Catalog\PictureRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Storage\FileStorageInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Tests\Factory\Catalog\DoctrineProductFactory;
use App\Tests\Support\DatabaseTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Adding a picture, with the real repositories and only the file storage
 * replaced: the picture is a row of its own, with its own dates.
 */
final class UploadProductImageUseCaseTest extends DatabaseTestCase
{
    use ClockSensitiveTrait;

    public function testThePictureGetsItsDatesAndTheProductKeepsItsOwn(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-11-01T09:30:00+00:00'));
        $productId = DoctrineProductFactory::createOne()->getId();
        $this->entityManager->clear();

        self::mockTime(new \DateTimeImmutable('2026-11-21T10:00:00+00:00'));
        $picture = $this->useCase()->execute($productId, new UploadedFile(__FILE__, 'bougie.jpg', test: true), 'Une bougie');
        $this->entityManager->clear();

        $storedPicture = $this->entityManager->find(DoctrinePicture::class, $picture->getId());
        self::assertEquals(new \DateTimeImmutable('2026-11-21T10:00:00+00:00'), $storedPicture?->getCreatedAt());
        self::assertEquals(
            new \DateTimeImmutable('2026-11-01T09:30:00+00:00'),
            $this->entityManager->find(DoctrineProduct::class, $productId)?->getUpdatedAt(),
            'Adding a picture does not change the product row.',
        );
    }

    private function useCase(): UploadProductImageUseCase
    {
        $storage = $this->createStub(FileStorageInterface::class);
        $storage->method('store')->willReturn('products/bougie.jpg');

        return new UploadProductImageUseCase(
            self::getContainer()->get(ProductRepositoryInterface::class),
            $storage,
            self::getContainer()->get(PictureRepositoryInterface::class),
        );
    }
}
