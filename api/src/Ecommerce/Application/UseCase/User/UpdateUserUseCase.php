<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\UpdateUserRequestDto;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;

readonly class UpdateUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(string $id, UpdateUserRequestDto $dto): User
    {
        $user = $this->userRepository->findById($id);

        $user->updateProfile($dto->firstName, $dto->lastName);

        if (null !== $dto->phoneNumber) {
            $user->updatePhoneNumber(new PhoneNumber($dto->phoneNumber));
        }

        $this->userRepository->save($user);

        // The dates are recorded when the account is saved: return it as stored,
        // or the caller would get the updated_at it had before this change.
        return $this->userRepository->findById($id);
    }
}
