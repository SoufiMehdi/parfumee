<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Service\User;

use App\Ecommerce\Domain\Service\User\RefreshTokenRevokerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

class GesdinetRefreshTokenRevoker implements RefreshTokenRevokerInterface
{
    public function __construct(
        private RefreshTokenManagerInterface $manager,
    ) {
    }

    public function revoke(string $refreshToken): void
    {
        // The manager hashes the value it is given before looking it up: tokens are stored hashed.
        $stored = $this->manager->get($refreshToken);

        if (null !== $stored) {
            $this->manager->delete($stored);
        }
    }
}
