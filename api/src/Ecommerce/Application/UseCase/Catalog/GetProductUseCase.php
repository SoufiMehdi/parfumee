<?php
namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;

class GetProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {
    }

    public function execute(?string $id): ?Product
    {
        if (null === $id) {
            return null;
        }

        return $this->productRepository->findById($id);
    }
}