<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\RevokeRefreshTokenRequestDto;
use App\Ecommerce\Domain\Service\User\RefreshTokenRevokerInterface;

/**
 * Signing out: the refresh token of the session can no longer be used.
 */
readonly class RevokeRefreshTokenUseCase
{
    public function __construct(
        private RefreshTokenRevokerInterface $refreshTokenRevoker,
    ) {
    }

    public function execute(RevokeRefreshTokenRequestDto $dto): void
    {
        $this->refreshTokenRevoker->revoke($dto->refreshToken);
    }
}
