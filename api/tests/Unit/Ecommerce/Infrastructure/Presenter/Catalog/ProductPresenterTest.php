<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Attribute;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Model\Catalog\Picture;
use App\Ecommerce\Domain\Model\Catalog\Product;
use App\Ecommerce\Infrastructure\Presenter\Catalog\ProductPresenter;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\AttributesView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategorySummaryView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\PictureView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\ProductView;
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

    public function testPresentCollectionKeepsTheOrderAndReturnsAList(): void
    {
        $category = new Category('category-id', 'Bougies', 'bougies');
        $products = [
            5 => new Product('first', 'A', 'a', 1.0, $category),
            9 => new Product('second', 'B', 'b', 2.0, $category),
        ];

        $views = $this->presenter->presentCollection($products);

        // A list, whatever the keys of the input: it must be encoded as a JSON array.
        self::assertSame([0, 1], array_keys($views));
        self::assertSame(['first', 'second'], [$views[0]->id, $views[1]->id]);
    }

    public function testPresentCollectionOfNothingIsAnEmptyList(): void
    {
        self::assertSame([], $this->presenter->presentCollection([]));
    }
}
