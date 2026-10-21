<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Service\User;

interface TokenGeneratorInterface
{
    /**
     * Generates a token string for the given user identifier and roles.
     */
    /**
     * @param string[] $roles
     */
    public function generate(string $userIdentifier, array $roles): string;
}
