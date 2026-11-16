<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\Catalog\View;

/**
 * A picture of a product.
 */
final readonly class PictureView
{
    public function __construct(
        public string $id,
        /** Path of the file, relative to the uploads directory. */
        public string $url,
        public ?string $alt,
        public ?int $sortOrder,
    ) {
    }
}
