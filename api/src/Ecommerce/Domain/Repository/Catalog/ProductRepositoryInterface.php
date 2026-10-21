<?php

namespace App\Ecommerce\Domain\Repository\Catalog;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Domain\Model\Catalog\Product;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;
    public function findById(string $id): ?Product;
    public function findAll(): array;
    public function findByFilter(GetProductsFilterDto $filter): array;
}
