<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;

class GetProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @param non-empty-list<ProductStatus> $statuses the statuses the caller may see. A product in another
     *                                                status is not found: the shop cannot tell a draft from
     *                                                a product that does not exist.
     */
    public function execute(string $id, array $statuses): Product
    {
        $product = $this->productRepository->findById($id);
        if (null === $product || !\in_array($product->getStatus(), $statuses, true)) {
            throw new ProductNotFoundException($id);
        }

        return $product;
    }
}
