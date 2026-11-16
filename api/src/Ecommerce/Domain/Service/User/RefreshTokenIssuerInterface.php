<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Service\User;

interface RefreshTokenIssuerInterface
{
    /**
     * Creates and stores a refresh token for the given user identifier, and returns
     * its value. It is the only time the value is known: it cannot be read back later.
     */
    public function issue(string $userIdentifier): string;
}
