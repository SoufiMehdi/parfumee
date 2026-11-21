<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\ListUsersQueryDto;
use App\Ecommerce\Domain\Exception\InvalidListQueryException;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use App\Ecommerce\Domain\Repository\User\UserCriteria;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Repository\User\UserSortField;

/**
 * One page of the accounts, for the back-office.
 */
readonly class ListUsersUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @return Page<User>
     */
    public function execute(ListUsersQueryDto $query): Page
    {
        // An empty search box sends "q=": it means no search, not a search for nothing.
        $search = trim($query->q ?? '');

        return $this->userRepository->findPage(
            new UserCriteria(
                search: '' === $search ? null : $search,
                sortBy: UserSortField::tryFrom($query->sort)
                    ?? throw new InvalidListQueryException(\sprintf('Accounts cannot be sorted by "%s".', $query->sort)),
                direction: SortDirection::tryFrom($query->order)
                    ?? throw new InvalidListQueryException(\sprintf('The sort order must be "asc" or "desc", not "%s".', $query->order)),
            ),
            new Pagination($query->page, $query->perPage),
        );
    }
}
