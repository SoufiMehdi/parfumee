<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DoctrineUser>
 */
final class DoctrineUserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DoctrineUser::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v4()->toRfc4122(),
            'email' => self::faker()->unique()->safeEmail(),
            // Not a real hash: use a real hasher in the tests that check a password.
            'password' => 'hashed-password',
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'roles' => ['ROLE_USER'],
            'phoneNumber' => null,
        ];
    }

    public function admin(): static
    {
        return $this->with(['roles' => ['ROLE_ADMIN']]);
    }
}
