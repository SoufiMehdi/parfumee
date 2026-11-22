<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\RevokeRefreshTokenRequestDto;
use App\Ecommerce\Application\UseCase\User\RevokeRefreshTokenUseCase;
use App\Ecommerce\Domain\Service\User\RefreshTokenRevokerInterface;
use PHPUnit\Framework\TestCase;

final class RevokeRefreshTokenUseCaseTest extends TestCase
{
    public function testTheRefreshTokenOfTheSessionIsRevoked(): void
    {
        $revoker = $this->createMock(RefreshTokenRevokerInterface::class);
        $revoker->expects(self::once())->method('revoke')->with('the-refresh-token');

        (new RevokeRefreshTokenUseCase($revoker))->execute(new RevokeRefreshTokenRequestDto('the-refresh-token'));
    }
}
