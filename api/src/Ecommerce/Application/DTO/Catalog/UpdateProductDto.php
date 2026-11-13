<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\Catalog;

/**
 * A product is replaced as a whole (PUT): same fields and rules as a creation.
 */
readonly class UpdateProductDto extends CreateProductDto
{
}
