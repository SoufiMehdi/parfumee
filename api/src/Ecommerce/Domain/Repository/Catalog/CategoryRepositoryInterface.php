<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\Catalog;

use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;

interface CategoryRepositoryInterface
{
    /**
     * Creates the category, or updates it when it already exists.
     */
    public function save(Category $category): void;

    public function delete(Category $category): void;

    public function findById(string $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    /**
     * @return Category[]
     */
    public function findAll(): array;

    /**
     * @return Page<Category>
     */
    public function findPage(CategoryCriteria $criteria, Pagination $pagination): Page;

    /**
     * Tells whether a category other than $exceptCategoryId already uses this slug.
     */
    public function slugExists(string $slug, ?string $exceptCategoryId = null): bool;

    public function hasChildren(string $id): bool;

    /**
     * Whatever their status: an archived product still belongs to its category.
     */
    public function hasProducts(string $id): bool;
}
