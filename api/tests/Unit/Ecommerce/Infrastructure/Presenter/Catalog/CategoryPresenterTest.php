<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Infrastructure\Presenter\Catalog\CategoryPresenter;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategorySummaryView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategoryView;
use PHPUnit\Framework\TestCase;

final class CategoryPresenterTest extends TestCase
{
    private const string CREATED_AT = '2026-11-01T09:30:00+00:00';
    private const string UPDATED_AT = '2026-11-20T18:00:00+00:00';

    public function testAChildCategoryIsPresentedWithItsParent(): void
    {
        $child = new Category('cat-2', 'Fondants', 'fondants', new Category('cat-1', 'Bougies', 'bougies'), new \DateTimeImmutable(self::CREATED_AT), new \DateTimeImmutable(self::UPDATED_AT));

        self::assertEquals(new CategoryView(
            'cat-2',
            'Fondants',
            'fondants',
            new CategorySummaryView('cat-1', 'Bougies'),
            new \DateTimeImmutable(self::CREATED_AT),
            new \DateTimeImmutable(self::UPDATED_AT),
        ), (new CategoryPresenter())->present($child));
    }

    public function testATopLevelCategoryHasNoParent(): void
    {
        $category = new Category('cat-1', 'Bougies', 'bougies', null, new \DateTimeImmutable(self::CREATED_AT), new \DateTimeImmutable(self::UPDATED_AT));

        $list = (new CategoryPresenter())->presentPage(new Page([$category], 41, new Pagination(page: 1, perPage: 1)));

        self::assertNull($list->items[0]->parent);
        self::assertSame(41, $list->pagination->total);
    }

    public function testACategoryNeverSavedCannotBePresented(): void
    {
        $this->expectException(\LogicException::class);

        (new CategoryPresenter())->present(new Category('cat-1', 'Bougies', 'bougies'));
    }
}
