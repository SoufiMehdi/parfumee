<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;

class GetCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    public function execute(string $id): Category
    {
        return $this->categoryRepository->findById($id) ?? throw new CategoryNotFoundException($id);
    }
}
