<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\Service\Catalog;

use App\Ecommerce\Domain\Repository\Catalog\CategoryRepositoryInterface;
use App\Ecommerce\Domain\Service\Catalog\SlugGeneratorInterface;

/**
 * Builds the slug of a category from its name and makes it unique among the categories.
 */
class CategorySlugResolver
{
    public const string FALLBACK = 'category';

    /** Leaves room for the "-2", "-3"… suffix in the 255-character column. */
    private const int MAX_BASE_LENGTH = 240;

    public function __construct(
        private SlugGeneratorInterface $slugGenerator,
        private CategoryRepositoryInterface $categoryRepository,
    ) {
    }

    /**
     * @param string|null $categoryId the category being renamed, so that it does not collide with itself
     */
    public function resolve(string $name, ?string $categoryId = null): string
    {
        $base = rtrim(substr($this->slugGenerator->generate($name), 0, self::MAX_BASE_LENGTH), '-');
        if ('' === $base) {
            $base = self::FALLBACK;
        }

        $slug = $base;
        for ($suffix = 2; $this->categoryRepository->slugExists($slug, $categoryId); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
