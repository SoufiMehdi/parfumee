<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Service\User;

interface PasswordHasherInterface
{
    public function hash(string $plainPassword): string;
}