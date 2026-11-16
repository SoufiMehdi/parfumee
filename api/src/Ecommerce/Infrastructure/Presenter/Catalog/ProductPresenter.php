<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\AttributesView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategorySummaryView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\PictureView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductView;

/**
 * Turns a domain product into what the API returns. The views are also what
 * the OpenAPI contract is generated from: the documentation cannot drift from the response.
 */
class ProductPresenter
{
    public function present(Product $product): ProductView
    {
        $attributes = $product->getAttributes();

        return new ProductView(
            $product->getId(),
            $product->getName(),
            $product->getSlug(),
            $product->getPrice(),
            new CategorySummaryView($product->getCategory()->getId(), $product->getCategory()->getName()),
            new AttributesView(
                $attributes?->getWeight(),
                $attributes?->getFragrance(),
                $attributes?->getSize(),
                $attributes?->getDescription(),
                $attributes?->getPresentation(),
            ),
            array_values(array_map(
                static fn (Picture $picture): PictureView => new PictureView($picture->getId(), $picture->url(), $picture->getAlt(), $picture->getSortOrder()),
                $product->getPictures(),
            )),
        );
    }

    /**
     * @param Product[] $products
     *
     * @return list<ProductView>
     */
    public function presentCollection(array $products): array
    {
        return array_values(array_map($this->present(...), $products));
    }
}
