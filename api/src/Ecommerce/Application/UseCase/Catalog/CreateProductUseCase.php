<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Application\Mapper\AttributesMapper;
use App\Ecommerce\Application\Service\Catalog\ProductSlugResolver;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use Symfony\Component\Uid\Uuid;

class CreateProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private AttributesMapper $attributesMapper,
        private ProductSlugResolver $slugResolver,
    ) {
    }

    public function execute(CreateProductDto $dto): ?string
    {
        // 1. Récupérer la catégorie depuis la base de données
        $category = $this->categoryRepository->findById($dto->categoryId);
        if (!$category) {
            throw new CategoryNotFoundException($dto->categoryId);
        }

        // 2. Créer le produit en utilisant le modèle Domaine
        $product = new Product(
            Uuid::v4()->toRfc4122(), // Génère un nouvel ID pour le produit
            $dto->name,
            $this->slugResolver->resolve($dto->name),
            $dto->price,
            $category,
            $this->attributesMapper->fromDto($dto),
            status: null === $dto->status ? ProductStatus::DRAFT : ProductStatus::fromValue($dto->status),
        );
        // 3. Enregistrer le produit en base de données via le repository
        $this->productRepository->save($product);

        return $product->getId();
    }
}
