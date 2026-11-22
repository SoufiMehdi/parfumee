<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\Catalog;

use App\Ecommerce\Domain\Model\Catalog\ProductStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Status filter of the back-office product list. The shop has none: it sees the published products only.
 */
final readonly class ProductStatusFilterDto
{
    public function __construct(
        /** Left out: the drafts and the published products, without the archived ones. */
        #[Assert\Choice(callback: [ProductStatus::class, 'values'])]
        public ?string $status = null,
    ) {
    }

    /**
     * @return non-empty-list<ProductStatus>
     */
    public function statuses(): array
    {
        return null === $this->status ? ProductStatus::managed() : [ProductStatus::fromValue($this->status)];
    }
}
