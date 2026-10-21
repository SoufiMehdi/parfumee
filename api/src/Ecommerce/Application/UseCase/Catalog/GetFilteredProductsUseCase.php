<?php

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Application\Mapper\GetProductsFilterMapper;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;

class GetFilteredProductsUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private GetProductsFilterMapper $filterMapper
    ) {
    }

    public function execute(array $data): array
    {
        $filterDto = $this->filterMapper->map($data);
        return $this->productRepository->findByFilter($filterDto);
    }
}
