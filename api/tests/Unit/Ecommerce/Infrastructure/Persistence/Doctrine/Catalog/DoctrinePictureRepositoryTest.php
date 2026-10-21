<?php

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog\DoctrinePictureRepository;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\PictureMapper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DoctrinePictureRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private PictureMapper&MockObject $mapper;
    private DoctrinePictureRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mapper = $this->createMock(PictureMapper::class);
        $this->repository = new DoctrinePictureRepository($this->entityManager, $this->mapper);
    }

    public function testSavePersistsAndFlushesPictureWithProduct(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('prod-123');
        
        $picture = $this->createMock(Picture::class);
        $picture->method('getProduct')->willReturn($product);

        $productEntity = $this->createMock(DoctrineProduct::class);
        $pictureEntity = $this->createMock(DoctrinePicture::class);

        $productRepo = $this->createMock(EntityRepository::class);
        $productRepo->expects($this->once())->method('find')->with('prod-123')->willReturn($productEntity);

        $this->entityManager->method('getRepository')->with(DoctrineProduct::class)->willReturn($productRepo);
        
        $this->mapper->expects($this->once())
            ->method('toInfrastructure')
            ->with($picture, $productEntity)
            ->willReturn($pictureEntity);

        $this->entityManager->expects($this->once())->method('persist')->with($pictureEntity);
        $this->entityManager->expects($this->once())->method('flush');

        $this->repository->save($picture);
    }

    public function testSavePersistsAndFlushesPictureWithoutProduct(): void
    {
        $picture = $this->createMock(Picture::class);
        $picture->method('getProduct')->willReturn(null);

        $pictureEntity = $this->createMock(DoctrinePicture::class);

        $this->entityManager->expects($this->never())->method('getRepository');
        
        $this->mapper->expects($this->once())
            ->method('toInfrastructure')
            ->with($picture, null)
            ->willReturn($pictureEntity);

        $this->entityManager->expects($this->once())->method('persist')->with($pictureEntity);
        $this->entityManager->expects($this->once())->method('flush');

        $this->repository->save($picture);
    }

    public function testFindByIdReturnsPicture(): void
    {
        $id = 'pic-123';
        $picture = $this->createMock(Picture::class);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('find')->with($id)->willReturn($picture);

        $this->entityManager->method('getRepository')->with(Picture::class)->willReturn($repo);

        $this->assertSame($picture, $this->repository->findById($id));
    }

    public function testFindByProductIdReturnsPictures(): void
    {
        $productId = 'prod-123';
        $pictures = [$this->createMock(Picture::class)];

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->with(['productId' => $productId])->willReturn($pictures);

        $this->entityManager->method('getRepository')->with(Picture::class)->willReturn($repo);

        $this->assertSame($pictures, $this->repository->findByProductId($productId));
    }

    public function testFindAllReturnsPictures(): void
    {
        $pictures = [$this->createMock(Picture::class)];

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findAll')->willReturn($pictures);

        $this->entityManager->method('getRepository')->with(Picture::class)->willReturn($repo);

        $this->assertSame($pictures, $this->repository->findAll());
    }

    public function testSaveThrowsExceptionOnDatabaseError(): void
    {
        
        $picture = $this->createMock(Picture::class);
        $this->mapper->method('toInfrastructure')->willReturn(new DoctrinePicture(
            'pic-123',
            'http://example.com/pic.jpg',
            null
        ));
        
        $this->entityManager->method('persist')->willThrowException(new \RuntimeException('DB Error'));

        $this->expectException(\RuntimeException::class);
        $this->repository->save($picture);
    }
}