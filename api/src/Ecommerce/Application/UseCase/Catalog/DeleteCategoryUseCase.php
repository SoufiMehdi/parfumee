<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\CategoryNotEmptyException;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;

/**
 * Deletes an empty category. One that still holds a child category or a product is kept
 * (409): nothing in the catalogue is ever left without a category.
 */
class DeleteCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function execute(string $id): void
    {
        $category = $this->categoryRepository->findById($id) ?? throw new CategoryNotFoundException($id);

        if ($this->categoryRepository->hasChildren($id)) {
            throw CategoryNotEmptyException::withChildren($id);
        }
        if ($this->categoryRepository->hasProducts($id)) {
            throw CategoryNotEmptyException::withProducts($id);
        }

        $this->categoryRepository->delete($category);
    }
}
