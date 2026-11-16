<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\Catalog;

use App\Ecommerce\Application\DTO\Catalog\GetProductsFilterDto;
use App\Ecommerce\Domain\Model\Catalog\Product;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(string $id): ?Product;

    public function findAll(): array;

    /**
     * Tells whether a product other than $exceptProductId already uses this slug.
     */
    public function slugExists(string $slug, ?string $exceptProductId = null): bool;

    public function findByFilter(GetProductsFilterDto $filter): array;
}
