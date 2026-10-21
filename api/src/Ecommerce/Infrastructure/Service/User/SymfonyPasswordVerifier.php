<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Service\User;

use App\Ecommerce\Domain\Service\User\PasswordVerifierInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SymfonyPasswordVerifier implements PasswordVerifierInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function verify(string $email, string $plainPassword): bool
    {
        $doctrineUser = $this->entityManager->getRepository(DoctrineUser::class)
            ->findOneBy(['email' => $email]);

        if (!$doctrineUser) {
            return false;
        }

        return $this->passwordHasher->isPasswordValid($doctrineUser, $plainPassword);
    }
}
