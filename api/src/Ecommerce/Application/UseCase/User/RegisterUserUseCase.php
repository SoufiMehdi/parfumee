<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Domain\Exception\User\DuplicateEmailException;
use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Service\User\PasswordHasherInterface;
use App\Ecommerce\Application\DTO\User\RegisterUserRequestDto;
use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use Symfony\Component\Uid\Uuid;

readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher
    ) {}

    public function execute(RegisterUserRequestDto $dto): void
    {
        // 1. Vérifier si l'email est déjà utilisé
        $existingUser = $this->userRepository->findByEmail($dto->email);
        if ($existingUser) {
            throw new DuplicateEmailException($dto->email);
        }

        // 2. Création de l'entité
        $hashedPassword = $this->passwordHasher->hash($dto->password);
        $user = new User(
            Uuid::v4()->toRfc4122(),
            new Email($dto->email),
            $hashedPassword,
            $dto->firstName,
            $dto->lastName,
            null !== $dto->phoneNumber ? new PhoneNumber($dto->phoneNumber) : null,
        );

        if (null !== $dto->address) {
            // The first address of an account is its default one.
            $user->addAddress(new Address(
                Uuid::v4()->toRfc4122(),
                $dto->address->type,
                $dto->address->street,
                $dto->address->city,
                $dto->address->postalCode,
                $dto->address->country,
                true,
            ));
        }

        // 3. Persistance
        $this->userRepository->save($user);
    }
}