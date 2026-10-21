<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Service\User;

use App\Ecommerce\Domain\Service\User\PasswordHasherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

class PasswordHasher implements PasswordHasherInterface
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function hash(string $plainPassword): string
    {
        $dummyUser = new InMemoryUser('dummy', 'dummy');

        return $this->passwordHasher->hashPassword($dummyUser, $plainPassword);
    }
}