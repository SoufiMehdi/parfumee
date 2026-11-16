<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\CreateBackOfficeUserDto;
use App\Ecommerce\Domain\Exception\User\DuplicateEmailException;
use App\Ecommerce\Domain\Model\User\Role;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Service\User\PasswordHasherInterface;
use App\Ecommerce\Domain\ValueObject\User\Email;
use Symfony\Component\Uid\Uuid;

/**
 * Creates an account that can sign in to the back-office (administrator or editor).
 * It is not reachable over HTTP: only the console command calls it.
 */
class CreateBackOfficeUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * @return string the id of the new account
     */
    public function execute(CreateBackOfficeUserDto $dto): string
    {
        if (null !== $this->userRepository->findByEmail($dto->email)) {
            throw new DuplicateEmailException($dto->email);
        }

        $user = new User(
            Uuid::v4()->toRfc4122(),
            new Email($dto->email),
            $this->passwordHasher->hash($dto->password),
            $dto->firstName,
            $dto->lastName,
            roles: [Role::USER],
        );
        $user->grantRole($dto->role);

        $this->userRepository->save($user);

        return $user->getId();
    }
}
