<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategoryListView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategorySummaryView;
use App\Ecommerce\Infrastructure\Presenter\Catalog\View\CategoryView;
use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;

class CategoryPresenter
{
    public function present(Category $category): CategoryView
    {
        $parent = $category->getParent();

        return new CategoryView(
            $category->getId(),
            $category->getName(),
            $category->getSlug(),
            null === $parent ? null : new CategorySummaryView($parent->getId(), $parent->getName()),
            self::savedDate($category->getCreatedAt()),
            self::savedDate($category->getUpdatedAt()),
        );
    }

    /**
     * @param Page<Category> $page
     */
    public function presentPage(Page $page): CategoryListView
    {
        return new CategoryListView(array_map($this->present(...), $page->items), PaginationView::of($page));
    }

    /**
     * The use cases only hand saved categories to the presenter, so the dates are
     * always there. A missing one is a bug, not a client error: it answers 500.
     */
    private static function savedDate(?\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date ?? throw new \LogicException('A category is presented before it is saved: it has no dates yet.');
    }
}
