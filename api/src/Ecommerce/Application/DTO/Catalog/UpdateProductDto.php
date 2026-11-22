<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\Catalog;

/**
 * A product is replaced as a whole (PUT): same fields and rules as a creation. The status
 * alone may be left out, and is then kept.
 */
readonly class UpdateProductDto extends CreateProductDto
{
}
