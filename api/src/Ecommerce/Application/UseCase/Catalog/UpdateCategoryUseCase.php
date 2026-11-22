<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\UpdateCategoryDto;
use App\Ecommerce\Application\Service\Catalog\CategorySlugResolver;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Exception\Catalog\InvalidCategoryParentException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;

class UpdateCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private CategorySlugResolver $slugResolver,
    ) {
    }

    public function execute(string $id, UpdateCategoryDto $dto): Category
    {
        $category = $this->categoryRepository->findById($id) ?? throw new CategoryNotFoundException($id);

        $parent = null === $dto->parentId
            ? null
            : $this->categoryRepository->findById($dto->parentId) ?? throw new CategoryNotFoundException($dto->parentId);
        // Two levels only: a category that holds others cannot go under a third one.
        if (null !== $parent && $this->categoryRepository->hasChildren($id)) {
            throw InvalidCategoryParentException::hasChildren($id);
        }
        $category->moveUnder($parent);

        // The slug follows the name, and only changes when the name does.
        if ($dto->name !== $category->getName()) {
            $category->rename($dto->name, $this->slugResolver->resolve($dto->name, $id));
        }

        $this->categoryRepository->save($category);

        // The dates are recorded when the category is saved: return it as stored.
        return $this->categoryRepository->findById($id) ?? throw new CategoryNotFoundException($id);
    }
}
