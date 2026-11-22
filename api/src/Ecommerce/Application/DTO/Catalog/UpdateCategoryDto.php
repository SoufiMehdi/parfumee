<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\Catalog;

/**
 * A category is replaced as a whole (PUT): same fields and rules as a creation. Without
 * parent_id, the category becomes (or stays) a top-level one.
 */
readonly class UpdateCategoryDto extends CreateCategoryDto
{
}
