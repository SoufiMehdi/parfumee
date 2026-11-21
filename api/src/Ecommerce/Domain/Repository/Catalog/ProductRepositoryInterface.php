<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(string $id): ?Product;

    /**
     * @return Product[]
     */
    public function findAll(): array;

    /**
     * Tells whether a product other than $exceptProductId already uses this slug.
     */
    public function slugExists(string $slug, ?string $exceptProductId = null): bool;

    /**
     * @return Page<Product>
     */
    public function findPage(ProductCriteria $criteria, Pagination $pagination): Page;
}
