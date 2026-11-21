<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\AttributesView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategorySummaryView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\PictureView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductListView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductView;
use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;

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
            self::savedDate($product->getCreatedAt()),
            self::savedDate($product->getUpdatedAt()),
        );
    }

    /**
     * @param Page<Product> $page
     */
    public function presentPage(Page $page): ProductListView
    {
        return new ProductListView(array_map($this->present(...), $page->items), PaginationView::of($page));
    }

    /**
     * The use cases only hand saved products to the presenter, so the dates are
     * always there. A missing one is a bug, not a client error: it answers 500.
     */
    private static function savedDate(?\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date ?? throw new \LogicException('A product is presented before it is saved: it has no dates yet.');
    }
}
