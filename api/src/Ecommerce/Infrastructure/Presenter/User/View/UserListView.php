<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\User\View;

use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;

/**
 * One page of accounts.
 */
final readonly class UserListView
{
    public function __construct(
        /** @var list<UserView> */
        public array $items,
        public PaginationView $pagination,
    ) {
    }
}
