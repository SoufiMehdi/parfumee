<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;

class GetProductsUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function execute(): array
    {
        return $this->productRepository->findAll();
    }
}
