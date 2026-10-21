<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDTO;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Service\User\PasswordVerifierInterface;
use App\Ecommerce\Domain\Service\User\TokenGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

readonly class LoginUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordVerifierInterface $passwordVerifier,
        private TokenGeneratorInterface $tokenGenerator,
    ) {
    }

    /**
     * @return array{token: string}
     */
    public function execute(LoginUserRequestDTO $dto): array
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (!$user) {
            throw new AuthenticationException('Invalid credentials.');
        }

        if (!$this->passwordVerifier->verify($dto->email, $dto->password)) {
            throw new AuthenticationException('Invalid credentials.');
        }

        $token = $this->tokenGenerator->generate(
            $user->getEmail()->getValue(),
            $user->getRoles()
        );

        return ['token' => $token];
    }
}
