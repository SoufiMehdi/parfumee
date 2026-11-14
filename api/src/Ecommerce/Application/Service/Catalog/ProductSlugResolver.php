<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\Service\Catalog;

use App\Ecommerce\Domain\Repository\Catalog\ProductRepositoryInterface;
use App\Ecommerce\Domain\Service\Catalog\SlugGeneratorInterface;

/**
 * Builds the slug of a product from its name and makes it unique in the catalog.
 */
class ProductSlugResolver
{
    public const string FALLBACK = 'product';

    /** Leaves room for the "-2", "-3"… suffix in the 255-character column. */
    private const int MAX_BASE_LENGTH = 240;

    public function __construct(
        private SlugGeneratorInterface $slugGenerator,
        private ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @param string|null $productId the product being renamed, so that it does not collide with itself
     */
    public function resolve(string $name, ?string $productId = null): string
    {
        $base = rtrim(substr($this->slugGenerator->generate($name), 0, self::MAX_BASE_LENGTH), '-');
        if ('' === $base) {
            $base = self::FALLBACK;
        }

        $slug = $base;
        for ($suffix = 2; $this->productRepository->slugExists($slug, $productId); ++$suffix) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
