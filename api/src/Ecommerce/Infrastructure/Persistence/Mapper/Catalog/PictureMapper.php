<?php

namespace App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrinePicture;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use Doctrine\ORM\EntityManagerInterface;

class PictureMapper
{
   public function __construct(
    private EntityManagerInterface $entityManager,
    ) {
        
    }

    public function toInfrastructure(Picture $picture, ?DoctrineProduct $productEntity = null): DoctrinePicture
    {   

        return new DoctrinePicture(
            $picture->getId(),
            $picture->url(),
            $picture->getAlt(),
            $picture->getSortOrder(),
            $productEntity,
            null
        );
    }
    public function toDomain(DoctrinePicture $doctrinePicture): Picture
    {
        return new Picture(
            $doctrinePicture->getId(),
            $doctrinePicture->getUrl(),
            $doctrinePicture->getAlt(),
            $doctrinePicture->getSortOrder()
        );
    }
}