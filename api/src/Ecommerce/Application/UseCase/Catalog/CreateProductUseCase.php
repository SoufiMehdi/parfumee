<?php

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Domain\Exception\Catalog\CategorieNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Application\Mapper\AttributesMapper;
use Symfony\Component\Uid\Uuid;

class CreateProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository,
        private AttributesMapper $attributesMapper
    ) {
    }

    public function execute(CreateProductDto $dto): ?string
    {
        // 1. Récupérer la catégorie depuis la base de données
        $category = $this->categoryRepository->findById(Uuid::fromString($dto->categoryId));
        if (!$category) {
            throw new CategorieNotFoundException($dto->categoryId);
        }
        
        // 2. Créer le produit en utilisant le modèle Domaine
        $product = new Product(
            Uuid::v4()->toRfc4122(), // Génère un nouvel ID pour le produit
            $dto->name,
            strtolower(str_replace(' ', '-', $dto->name)), // Génère un slug simple à partir du nom
            $dto->price,
            $category,
            $this->attributesMapper->fromDto($dto)
        );
        // 3. Enregistrer le produit en base de données via le repository
        $this->productRepository->save($product);
        return $product->getId();
    }
}