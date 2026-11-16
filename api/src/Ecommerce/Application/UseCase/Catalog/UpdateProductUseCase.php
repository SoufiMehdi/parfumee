<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Application\Mapper\AttributesMapper;
use App\Ecommerce\Application\Service\Catalog\ProductSlugResolver;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;

class UpdateProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private AttributesMapper $attributesMapper,
        private ProductSlugResolver $slugResolver,
    ) {
    }

    public function execute(UpdateProductDto $dto, string $id): Product
    {
        $product = $this->productRepository->findById($id);
        if (!$product) {
            throw new ProductNotFoundException($id);
        }

        $category = $this->categoryRepository->findById($dto->categoryId);
        if (!$category) {
            throw new CategoryNotFoundException($dto->categoryId);
        }

        // The slug follows the name, and only changes when the name does.
        $slug = $dto->name === $product->getName()
            ? $product->getSlug()
            : $this->slugResolver->resolve($dto->name, $product->getId());

        $product->updateDetails(
            $dto->name,
            $slug,
            $dto->price,
            $category,
            $this->attributesMapper->fromDto($dto) // Utilisation de l'instance
        );

        $this->productRepository->save($product);

        return $product;
    }
}
