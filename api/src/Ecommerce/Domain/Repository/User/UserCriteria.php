<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\User;

use App\Ecommerce\Domain\Repository\SortDirection;

/**
 * Which accounts a list holds, and in which order.
 */
final readonly class UserCriteria
{
    public function __construct(
        /** Part of the email, the first name or the last name, whatever the case. */
        public ?string $search = null,
        public UserSortField $sortBy = UserSortField::EMAIL,
        public SortDirection $direction = SortDirection::ASC,
    ) {
    }
}
