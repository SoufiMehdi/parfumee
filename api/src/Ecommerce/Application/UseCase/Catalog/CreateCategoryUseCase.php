<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\Catalog;

use App\Ecommerce\Application\DTO\Catalog\CreateCategoryDto;
use App\Ecommerce\Application\Service\Catalog\CategorySlugResolver;
use App\Ecommerce\Domain\Exception\Catalog\CategoryNotFoundException;
use App\Ecommerce\Domain\Model\Catalog\Category;
use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use Symfony\Component\Uid\Uuid;

class CreateCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private CategorySlugResolver $slugResolver,
    ) {
    }

    /**
     * @return string the id of the new category
     */
    public function execute(CreateCategoryDto $dto): string
    {
        $parent = null === $dto->parentId
            ? null
            : $this->categoryRepository->findById($dto->parentId) ?? throw new CategoryNotFoundException($dto->parentId);

        $category = new Category(
            Uuid::v4()->toRfc4122(),
            $dto->name,
            $this->slugResolver->resolve($dto->name),
            $parent,
        );
        $this->categoryRepository->save($category);

        return $category->getId();
    }
}
