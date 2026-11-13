<?php

namespace App\Ecommerce\Domain\Repository\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;

interface PictureRepositoryInterface
{
    public function save(Picture $picture): void;
    public function findById(string $id): ?Picture;
    /**
     * @return Picture[]
     */
    public function findByProductId(string $productId): array;

    /**
     * @return Picture[]
     */
    public function findAll(): array;
}