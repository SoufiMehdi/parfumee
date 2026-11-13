<?php 

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\UpdateProductDto;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Exception\Catalog\ProductNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Application\Mapper\AttributesMapper;

class UpdateProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private AttributesMapper $attributesMapper // Injection ajoutée
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

        $product->updateDetails(
            $dto->name,
            $dto->price,
            $category,
            $this->attributesMapper->fromDto($dto) // Utilisation de l'instance
        );

        $this->productRepository->save($product);
        return $product;
    }
}