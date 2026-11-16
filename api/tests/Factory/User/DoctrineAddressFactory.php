<?php

declare(strict_types=1);

namespace App\Tests\Factory\User;

use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineAddress;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<DoctrineAddress>
 */
final class DoctrineAddressFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return DoctrineAddress::class;
    }

    protected function defaults(): array
    {
        return [
            'id' => Uuid::v4()->toRfc4122(),
            'user' => DoctrineUserFactory::new(),
            'type' => 'shipping',
            'street' => self::faker()->streetAddress(),
            'city' => self::faker()->city(),
            'postalCode' => self::faker()->postcode(),
            'country' => 'FR',
            'isDefault' => false,
        ];
    }
}
