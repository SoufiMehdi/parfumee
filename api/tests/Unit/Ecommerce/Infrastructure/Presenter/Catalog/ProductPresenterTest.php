<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
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
    // A product handed to the presenter has always been saved: it has its dates.
    private const string CREATED_AT = '2026-11-01T09:30:00+00:00';
    private const string UPDATED_AT = '2026-11-20T18:00:00+00:00';

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
            new \DateTimeImmutable(self::CREATED_AT),
            new \DateTimeImmutable(self::UPDATED_AT),
            ProductStatus::ARCHIVED,
        );

        $view = $this->presenter->present($product);

        self::assertEquals(new ProductView(
            'product-id',
            'Bougie Ambre',
            'bougie-ambre',
            24.9,
            new CategorySummaryView('category-id', 'Bougies'),
            new AttributesView(180.0, 'ambre', 'M', 'Cire de soja.', 'Verre ambré'),
            ProductStatus::ARCHIVED,
            [new PictureView('picture-id', 'products/a.jpg', 'Une bougie', 2)],
            new \DateTimeImmutable(self::CREATED_AT),
            new \DateTimeImmutable(self::UPDATED_AT),
        ), $view);
    }

    public function testAProductWithoutAttributesNorPicturesIsPresentedWithEmptyOnes(): void
    {
        $product = self::savedProduct('product-id', new Category('category-id', 'Bougies', 'bougies'));

        $view = $this->presenter->present($product);

        self::assertEquals(new AttributesView(null, null, null, null, null), $view->attributes);
        self::assertSame([], $view->pictures);
    }

    public function testAProductNeverSavedCannotBePresented(): void
    {
        // Without dates: a use case returned it before saving it, which is a bug (500).
        $product = new Product('product-id', 'Bougie', 'bougie', 10.0, new Category('category-id', 'Bougies', 'bougies'));

        $this->expectException(\LogicException::class);

        $this->presenter->present($product);
    }

    public function testPresentPageKeepsTheOrderOfTheItemsAndDescribesThePage(): void
    {
        $category = new Category('category-id', 'Bougies', 'bougies');
        $page = new Page(
            [self::savedProduct('first', $category), self::savedProduct('second', $category)],
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

    private static function savedProduct(string $id, Category $category): Product
    {
        return new Product(
            $id,
            'Bougie',
            'bougie-'.$id,
            10.0,
            $category,
            createdAt: new \DateTimeImmutable(self::CREATED_AT),
            updatedAt: new \DateTimeImmutable(self::UPDATED_AT),
        );
    }
}
