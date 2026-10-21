<?php

namespace App\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Product;

class ProductPresenter
{
    public function present(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'price' => $product->getPrice(),
            'category' => [
                'id' => $product->getCategory()->getId(),
                'name' => $product->getCategory()->getName()
            ],
            'attributes' => $product->getAttributes()->toArray(),
            'pictures' => array_map(fn($picture) => [
                'id' => $picture->getId(),
                'url' => $picture->url(),
                'alt' => $picture->getAlt(),
                'sortOrder' => $picture->getSortOrder()
            ], $product->getPictures())
        ];
    }
    /**
     * Transforme une liste de produits
     * @param Product[] $products
     */
    public function presentCollection(array $products): array
    {
        return array_map(fn(Product $product) => $this->present($product), $products);
    }
}