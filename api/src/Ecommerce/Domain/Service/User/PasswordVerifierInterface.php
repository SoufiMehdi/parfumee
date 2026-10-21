<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Service\User;

interface PasswordVerifierInterface
{
    /**
     * Verifies that the given plain text password matches the stored hash
     * for the user identified by email.
     */
    public function verify(string $email, string $plainPassword): bool;
}
