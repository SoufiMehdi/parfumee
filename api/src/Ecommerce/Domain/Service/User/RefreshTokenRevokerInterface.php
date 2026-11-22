<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Service\User;

/**
 * Ends a session on the server side: once revoked, a refresh token can no longer
 * be exchanged for a new JWT. The JWT already issued stays valid until it expires.
 */
interface RefreshTokenRevokerInterface
{
    /**
     * An unknown, expired or already revoked token is ignored: signing out twice,
     * or after the session expired, is not an error.
     */
    public function revoke(string $refreshToken): void;
}
