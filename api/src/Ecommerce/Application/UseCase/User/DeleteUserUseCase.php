<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use Symfony\Component\Uid\Uuid;

readonly class DeleteUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(string $id): void
    {
        $this->userRepository->delete(Uuid::fromString($id));
    }
}
