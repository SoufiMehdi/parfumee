<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Product;

class ProductPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'slug' => $product->getSlug(),
            'price' => $product->getPrice(),
            'category' => [
                'id' => $product->getCategory()->getId(),
                'name' => $product->getCategory()->getName(),
            ],
            'attributes' => $product->getAttributes()->toArray(),
            'pictures' => array_map(fn ($picture) => [
                'id' => $picture->getId(),
                'url' => $picture->url(),
                'alt' => $picture->getAlt(),
                'sortOrder' => $picture->getSortOrder(),
            ], $product->getPictures()),
        ];
    }

    /**
     * Transforme une liste de produits.
     *
     * @param Product[] $products
     *
     * @return list<array<string, mixed>>
     */
    public function presentCollection(array $products): array
    {
        return array_map(fn (Product $product) => $this->present($product), $products);
    }
}
