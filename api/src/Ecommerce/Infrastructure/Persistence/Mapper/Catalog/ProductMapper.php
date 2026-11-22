<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Mapper\Catalog;

use App\Ecommerce\Application\Mapper\AttributesMapper;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineCategory;
use App\Ecommerce\Infrastructure\Persistence\Entity\Catalog\DoctrineProduct;

class ProductMapper
{
    public function __construct(
        private CategoryMapper $categoryMapper,
        private AttributesMapper $attributesMapper,
        private PictureMapper $pictureMapper,
    ) {
        // Si tu as besoin d'injecter d'autres mappers ou services, tu peux le faire ici
    }

    /**
     * Transforme un modèle Domaine en entité Doctrine (Infrastructure).
     */
    public function toInfrastructure(Product $product, DoctrineCategory $category): DoctrineProduct
    {
        return new DoctrineProduct(
            $product->getId(),
            $product->getName(),
            $product->getSlug(),
            $product->getPrice(),
            $category,
            $this->attributesMapper->toDto($product->getAttributes()),
            $product->getStatus(),
        );
    }

    /**
     * Mappe un modèle Domaine vers une entité Doctrine.
     */
    public function mapDomainToEntity(Product $product, DoctrineProduct $doctrineProduct, DoctrineCategory $category): void
    {
        $doctrineProduct->setName($product->getName());
        $doctrineProduct->setSlug($product->getSlug());
        $doctrineProduct->setPrice($product->getPrice());
        $doctrineProduct->setCategory($category);
        $doctrineProduct->setAttributes($this->attributesMapper->toDto($product->getAttributes()));
        $doctrineProduct->setStatus($product->getStatus());
    }

    /**
     * Transforme une entité Doctrine en modèle Domaine.
     */
    public function toDomain(DoctrineProduct $doctrineProduct): Product
    {
        return new Product(
            $doctrineProduct->getId(),
            $doctrineProduct->getName(),
            $doctrineProduct->getSlug(),
            $doctrineProduct->getPrice(),
            $this->categoryMapper->toDomain($doctrineProduct->getCategory()),
            $this->attributesMapper->fromArray($doctrineProduct->getAttributes()),
            array_map(fn ($doctrinePicture) => $this->pictureMapper->toDomain($doctrinePicture), $doctrineProduct->getPictures()->toArray()),
            // Read only: the dates go from the entity to the model, never back (see Timestamps).
            createdAt: $doctrineProduct->getCreatedAt(),
            updatedAt: $doctrineProduct->getUpdatedAt(),
            status: $doctrineProduct->getStatus(),
        );
    }
}
