<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;

readonly class GetUsersUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * @return User[]
     */
    public function execute(): array
    {
        return $this->userRepository->findAll();
    }
}
