<?php

namespace App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateProductDto;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog\CategoryMapper;
use App\Ecommerce\Application\Mapper\AttributesMapper;

class ProductMapper
{
    public function __construct(
        private CategoryMapper $categoryMapper,
        private AttributesMapper $attributesMapper,
        private PictureMapper $pictureMapper
    )
    {
        // Si tu as besoin d'injecter d'autres mappers ou services, tu peux le faire ici
    }
    /**
     * Transforme un modèle Domaine en entité Doctrine (Infrastructure)
     */
    public function toInfrastructure(Product $product, DoctrineCategory $category): DoctrineProduct
    {
        return new DoctrineProduct(
            $product->getId(),
            $product->getName(),
            $product->getPrice(),
            $category,
            $this->attributesMapper->toDto($product->getAttributes())
        );
    }

    /**
     * Mappe un modèle Domaine vers une entité Doctrine
     */
    public function mapDomainToEntity(Product $product, DoctrineProduct $doctrineProduct, DoctrineCategory $category): void
    {
        $doctrineProduct->setName($product->getName());
        $doctrineProduct->setPrice($product->getPrice());
        $doctrineProduct->setCategory($category);
        $doctrineProduct->setAttributes($this->attributesMapper->toDto($product->getAttributes()));
        // Tu peux aussi mettre à jour le slug si tu le stockes dans DoctrineProduct
    }

    /**
     * Transforme une entité Doctrine en modèle Domaine
     */
    public function toDomain(DoctrineProduct $doctrineProduct): Product
    {
        return new Product(
            $doctrineProduct->getId(),
            $doctrineProduct->getName(),
            "slug-placeholder", // Tu peux ajouter le slug dans DoctrineProduct si besoin
            $doctrineProduct->getPrice(),
            $this->categoryMapper->toDomain($doctrineProduct->getCategory()),
            $this->attributesMapper->fromArray($doctrineProduct->getAttributes()),
            array_map(fn($doctrinePicture) => $this->pictureMapper->toDomain($doctrinePicture), $doctrineProduct->getPictures()->toArray())
        );
    }
}