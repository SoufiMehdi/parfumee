<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use App\Ecommerce\Domain\Repository\User\UserSortField;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Query string of the account list of the back-office. Every parameter is optional.
 */
final readonly class ListUsersQueryDto
{
    public function __construct(
        /** Part of the email, the first name or the last name, whatever the case. */
        #[Assert\Length(max: 255)]
        public ?string $q = null,
        /** Number of the page, from 1. */
        #[Assert\Range(min: 1, max: Pagination::MAX_PAGE)]
        public int $page = 1,
        /** Maximum number of accounts on a page. */
        #[Assert\Range(min: 1, max: Pagination::MAX_PER_PAGE)]
        public int $perPage = Pagination::DEFAULT_PER_PAGE,
        /** Field to sort by. Accounts with the same value are then sorted by id, so that pages never overlap. */
        #[Assert\Choice(callback: [UserSortField::class, 'values'])]
        public string $sort = UserSortField::EMAIL->value,
        #[Assert\Choice(callback: [SortDirection::class, 'values'])]
        public string $order = SortDirection::ASC->value,
    ) {
    }
}
