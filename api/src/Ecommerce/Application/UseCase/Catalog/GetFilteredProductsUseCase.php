<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;

class GetFilteredProductsUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @return Product[]
     */
    public function execute(GetProductsFilterDto $filter): array
    {
        return $this->productRepository->findByFilter($filter);
    }
}
