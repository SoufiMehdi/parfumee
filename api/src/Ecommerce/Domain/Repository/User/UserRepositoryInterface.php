<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\User;

use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;

/**
 * Interface respectant le principe d'Interface Segregation (ISP).
 */
interface UserRepositoryInterface
{
    /**
     * @throws UserNotFoundException
     */
    public function findById(string $id): User;

    /**
     * @return User[]
     */
    public function findAll(): array;

    /**
     * @return Page<User>
     */
    public function findPage(UserCriteria $criteria, Pagination $pagination): Page;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;

    /**
     * @throws UserNotFoundException
     */
    public function delete(string $id): void;
}
