<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Mapper\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\PictureMapper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PictureMapperTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private PictureMapper $mapper;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->mapper = new PictureMapper($this->entityManager);
    }

    public function testToInfrastructureWithProduct(): void
    {
        // 1. Arrange
        $picture = $this->createMock(Picture::class);
        $doctrineProduct = $this->createMock(DoctrineProduct::class);

        $picture->method('getId')->willReturn('img-uuid-123');
        $picture->method('url')->willReturn('https://cdn.com/perfume.jpg');
        $picture->method('getAlt')->willReturn('Flacon de parfum de face');
        $picture->method('getSortOrder')->willReturn(1);

        // 2. Act
        $result = $this->mapper->toInfrastructure($picture, $doctrineProduct);

        // 3. Assert
        $this->assertInstanceOf(DoctrinePicture::class, $result);
        $this->assertSame('img-uuid-123', $result->getId());
        $this->assertSame('https://cdn.com/perfume.jpg', $result->getUrl());
        $this->assertSame('Flacon de parfum de face', $result->getAlt());
        $this->assertSame(1, $result->getSortOrder());
    }

    public function testToInfrastructureWithoutProduct(): void
    {
        // 1. Arrange
        $picture = $this->createMock(Picture::class);

        // On teste le comportement si le produit passé est null
        $picture->method('getId')->willReturn('img-uuid-456');
        $picture->method('url')->willReturn('https://cdn.com/perfume-2.jpg');
        $picture->method('getAlt')->willReturn(null);
        $picture->method('getSortOrder')->willReturn(0);

        // 2. Act
        $result = $this->mapper->toInfrastructure($picture, null);

        // 3. Assert
        $this->assertInstanceOf(DoctrinePicture::class, $result);
        $this->assertSame('img-uuid-456', $result->getId());
        $this->assertNull($result->getAlt());
    }

    public function testToDomain(): void
    {
        // 1. Arrange
        $doctrinePicture = $this->createMock(DoctrinePicture::class);

        $doctrinePicture->method('getId')->willReturn('img-uuid-789');
        $doctrinePicture->method('getUrl')->willReturn('https://cdn.com/perfume-3.jpg');
        $doctrinePicture->method('getAlt')->willReturn('Vue d\'ambiance');
        $doctrinePicture->method('getSortOrder')->willReturn(2);

        // 2. Act
        $result = $this->mapper->toDomain($doctrinePicture);

        // 3. Assert
        $this->assertInstanceOf(Picture::class, $result);
        $this->assertSame('img-uuid-789', $result->getId());
        $this->assertSame('https://cdn.com/perfume-3.jpg', $result->url()); // Attention au nom de méthode dans ton domaine
        $this->assertSame('Vue d\'ambiance', $result->getAlt());
        $this->assertSame(2, $result->getSortOrder());
    }
}
