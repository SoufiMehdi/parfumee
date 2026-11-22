<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog\View;

/**
 * A category, as returned by the API.
 */
final readonly class CategoryView
{
    public function __construct(
        public string $id,
        public string $name,
        /** URL-safe name, unique among the categories. */
        public string $slug,
        /** The top-level category this one is filed under; null for a top-level category. */
        public ?CategorySummaryView $parent,
        /** When the category was created, in UTC. */
        public \DateTimeImmutable $createdAt,
        /** When the category last changed, in UTC. */
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
