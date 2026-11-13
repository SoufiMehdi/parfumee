<?php

namespace App\Ecommerce\Infrastructure\Persistence\Doctrine\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\PictureMapper;
use App\Ecommerce\Domain\Repository\Catalog\PictureRepositoryInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use Doctrine\ORM\EntityManagerInterface;

class DoctrinePictureRepository implements PictureRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PictureMapper $pictureMapper
        )
    {
    }
    public function save(Picture $picture): void
    {
        $productEntity = null;
        if ($picture->getProduct()) {
            $productEntity = $this->entityManager->getRepository(DoctrineProduct::class)
            ->find($picture->getProduct()->getId());
        }   
        
        $pictureEntity = $this->pictureMapper->toInfrastructure($picture, $productEntity);
        $this->entityManager->persist($pictureEntity);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?Picture
    {
        $pictureEntity = $this->entityManager->find(DoctrinePicture::class, $id);

        return $pictureEntity ? $this->pictureMapper->toDomain($pictureEntity) : null;
    }

    /**
     * @return Picture[]
     */
    public function findByProductId(string $productId): array
    {
        return $this->toDomainList(
            $this->entityManager->getRepository(DoctrinePicture::class)->findBy(['product' => $productId])
        );
    }

    /**
     * @return Picture[]
     */
    public function findAll(): array
    {
        return $this->toDomainList(
            $this->entityManager->getRepository(DoctrinePicture::class)->findAll()
        );
    }

    /**
     * @param DoctrinePicture[] $pictureEntities
     *
     * @return Picture[]
     */
    private function toDomainList(array $pictureEntities): array
    {
        return array_map($this->pictureMapper->toDomain(...), $pictureEntities);
    }
}
