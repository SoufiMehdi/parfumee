<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;

/**
 * Takes a product out of the catalogue. It is archived, never deleted: the orders that
 * sold it must still read it. Archiving an archived product is not an error.
 */
class ArchiveProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    public function execute(string $id): void
    {
        $product = $this->productRepository->findById($id) ?? throw new ProductNotFoundException($id);

        $product->archive();

        $this->productRepository->save($product);
    }
}
