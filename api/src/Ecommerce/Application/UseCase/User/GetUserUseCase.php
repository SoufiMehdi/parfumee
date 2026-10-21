<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use Symfony\Component\Uid\Uuid;

readonly class GetUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(string $id): User
    {
        return $this->userRepository->findById(Uuid::fromString($id));
    }
}
