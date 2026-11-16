<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;

/**
 * Gives a role to an existing account, found by its email. Its password is left untouched.
 */
class GrantRoleUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(string $email, string $role): void
    {
        $user = $this->userRepository->findByEmail($email);
        if (null === $user) {
            throw UserNotFoundException::withEmail($email);
        }

        $user->grantRole($role);

        $this->userRepository->save($user);
    }
}
