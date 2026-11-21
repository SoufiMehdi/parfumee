<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\AttributesView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategorySummaryView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\PictureView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductView;
use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;
use PHPUnit\Framework\TestCase;

final class ProductPresenterTest extends TestCase
{
    private ProductPresenter $presenter;

    protected function setUp(): void
    {
        $this->presenter = new ProductPresenter();
    }

    public function testPresentCopiesEveryFieldOfTheProduct(): void
    {
        $product = new Product(
            'product-id',
            'Bougie Ambre',
            'bougie-ambre',
            24.9,
            new Category('category-id', 'Bougies', 'bougies'),
            new Attribute(180.0, 'ambre', 'M', 'Cire de soja.', 'Verre ambré'),
            [new Picture('picture-id', 'products/a.jpg', 'Une bougie', 2)],
        );

        $view = $this->presenter->present($product);

        self::assertEquals(new ProductView(
            'product-id',
            'Bougie Ambre',
            'bougie-ambre',
            24.9,
            new CategorySummaryView('category-id', 'Bougies'),
            new AttributesView(180.0, 'ambre', 'M', 'Cire de soja.', 'Verre ambré'),
            [new PictureView('picture-id', 'products/a.jpg', 'Une bougie', 2)],
        ), $view);
    }

    public function testAProductWithoutAttributesNorPicturesIsPresentedWithEmptyOnes(): void
    {
        $product = new Product('product-id', 'Bougie', 'bougie', 10.0, new Category('category-id', 'Bougies', 'bougies'));

        $view = $this->presenter->present($product);

        self::assertEquals(new AttributesView(null, null, null, null, null), $view->attributes);
        self::assertSame([], $view->pictures);
    }

    public function testPresentPageKeepsTheOrderOfTheItemsAndDescribesThePage(): void
    {
        $category = new Category('category-id', 'Bougies', 'bougies');
        $page = new Page(
            [new Product('first', 'A', 'a', 1.0, $category), new Product('second', 'B', 'b', 2.0, $category)],
            total: 45,
            pagination: new Pagination(page: 2, perPage: 20),
        );

        $list = $this->presenter->presentPage($page);

        self::assertSame(['first', 'second'], [$list->items[0]->id, $list->items[1]->id]);
        self::assertEquals(new PaginationView(page: 2, perPage: 20, total: 45, totalPages: 3), $list->pagination);
        self::assertSame(['X-Total-Count' => '45'], $list->pagination->headers());
    }

    public function testAnEmptyPageIsPresentedWithNoItemsAndNoPage(): void
    {
        $list = $this->presenter->presentPage(new Page([], 0, new Pagination()));

        self::assertSame([], $list->items);
        self::assertEquals(new PaginationView(page: 1, perPage: Pagination::DEFAULT_PER_PAGE, total: 0, totalPages: 0), $list->pagination);
    }
}
