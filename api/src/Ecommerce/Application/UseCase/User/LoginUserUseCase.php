<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDto;
use App\Ecommerce\Domain\Exception\User\InvalidCredentialsException;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Service\User\PasswordVerifierInterface;
use App\Ecommerce\Domain\Service\User\RefreshTokenIssuerInterface;
use App\Ecommerce\Domain\Service\User\TokenGeneratorInterface;

readonly class LoginUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordVerifierInterface $passwordVerifier,
        private TokenGeneratorInterface $tokenGenerator,
        private RefreshTokenIssuerInterface $refreshTokenIssuer,
    ) {
    }

    /**
     * @return array{token: string, refresh_token: string}
     */
    public function execute(LoginUserRequestDto $dto): array
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (!$user) {
            throw new InvalidCredentialsException();
        }

        if (!$this->passwordVerifier->verify($dto->email, $dto->password)) {
            throw new InvalidCredentialsException();
        }

        $token = $this->tokenGenerator->generate(
            $user->getEmail()->getValue(),
            $user->getRoles()
        );

        return [
            'token' => $token,
            'refresh_token' => $this->refreshTokenIssuer->issue($user->getEmail()->getValue()),
        ];
    }
}
