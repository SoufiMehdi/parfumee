<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Application\UseCase;

use App\Ecommerce\Application\DTO\User\RegisterAddressDto;
use App\Ecommerce\Application\DTO\User\RegisterUserRequestDto;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Tests\Support\DatabaseTestCase;

/**
 * Covers B7 down to the database: the phone number and the address given at
 * registration used to be dropped.
 */
final class RegisterUserUseCaseTest extends DatabaseTestCase
{
    public function testPhoneNumberAndAddressAreStored(): void
    {
        self::getContainer()->get(RegisterUserUseCase::class)->execute(new RegisterUserRequestDto(
            email: 'ada@example.test',
            password: 'long-enough-password',
            firstName: 'Ada',
            lastName: 'Lovelace',
            phoneNumber: '+33 6 12 34 56 78',
            address: new RegisterAddressDto('1 rue de la Paix', 'Paris', '75002', 'France'),
        ));
        $this->entityManager->clear();

        $user = self::getContainer()->get(UserRepositoryInterface::class)->findByEmail('ada@example.test');

        self::assertNotNull($user);
        self::assertSame('+33 6 12 34 56 78', $user->getPhoneNumber()?->getValue());
        self::assertCount(1, $user->getAddresses());
        $address = $user->getAddresses()[0];
        self::assertSame('1 rue de la Paix', $address->getStreet());
        self::assertSame('Paris', $address->getCity());
        self::assertSame('75002', $address->getPostalCode());
        self::assertSame('France', $address->getCountry());
        self::assertSame(Address::TYPE_SHIPPING, $address->getType());
        self::assertTrue($address->isDefault());
        self::assertNotSame('long-enough-password', $user->getPasswordHash());
    }

    public function testRegistrationWithoutPhoneNorAddress(): void
    {
        self::getContainer()->get(RegisterUserUseCase::class)->execute(
            new RegisterUserRequestDto('grace@example.test', 'long-enough-password', 'Grace', 'Hopper'),
        );
        $this->entityManager->clear();

        $user = self::getContainer()->get(UserRepositoryInterface::class)->findByEmail('grace@example.test');

        self::assertNotNull($user);
        self::assertNull($user->getPhoneNumber());
        self::assertSame([], $user->getAddresses());
    }
}
