<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\UpdateUserRequestDTO;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use Symfony\Component\Uid\Uuid;

readonly class UpdateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(string $id, UpdateUserRequestDTO $dto): User
    {
        $user = $this->userRepository->findById(Uuid::fromString($id));

        $user->updateProfile($dto->firstName, $dto->lastName);

        if ($dto->phoneNumber !== null) {
            $user->updatePhoneNumber(new PhoneNumber($dto->phoneNumber));
        }

        $this->userRepository->save($user);

        return $user;
    }
}
