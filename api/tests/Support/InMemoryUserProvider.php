<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Replaces the Doctrine user provider in functional tests, so that the
 * firewall can be exercised without a database.
 *
 * @implements UserProviderInterface<DoctrineUser>
 */
final class InMemoryUserProvider implements UserProviderInterface
{
    /** @var array<string, DoctrineUser> */
    private array $users = [];

    public function add(DoctrineUser $user): void
    {
        $this->users[$user->getUserIdentifier()] = $user;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->users[$identifier] ?? throw new UserNotFoundException();
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return DoctrineUser::class === $class;
    }
}
