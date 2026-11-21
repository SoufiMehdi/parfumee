<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Persistence\Doctrine\User;

use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use App\Tests\Factory\User\DoctrineAddressFactory;
use App\Tests\Factory\User\DoctrineUserFactory;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

final class DoctrineUserRepositoryTest extends DatabaseTestCase
{
    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(UserRepositoryInterface::class);
    }

    public function testASavedUserIsReadBackWithEveryField(): void
    {
        $id = Uuid::v4()->toRfc4122();
        $addressId = Uuid::v4()->toRfc4122();

        $this->repository->save(new User(
            $id,
            new Email('ada@example.test'),
            'hashed-password',
            'Ada',
            'Lovelace',
            new PhoneNumber('+33612345678'),
            ['ROLE_USER', 'ROLE_ADMIN'],
            [new Address($addressId, Address::TYPE_BILLING, '1 rue des Bougies', 'Lyon', '69001', 'FR', true)],
        ));
        $this->entityManager->clear();

        $user = $this->repository->findById($id);

        self::assertSame($id, $user->getId());
        self::assertSame('ada@example.test', $user->getEmail()->getValue());
        self::assertSame('hashed-password', $user->getPasswordHash());
        self::assertSame('Ada', $user->getFirstName());
        self::assertSame('Lovelace', $user->getLastName());
        self::assertSame('+33612345678', $user->getPhoneNumber()?->getValue());
        self::assertContains('ROLE_ADMIN', $user->getRoles());

        self::assertCount(1, $user->getAddresses());
        $address = $user->getAddresses()[0];
        self::assertSame($addressId, $address->getId());
        self::assertSame(Address::TYPE_BILLING, $address->getType());
        self::assertSame('1 rue des Bougies', $address->getStreet());
        self::assertSame('Lyon', $address->getCity());
        self::assertSame('69001', $address->getPostalCode());
        self::assertSame('FR', $address->getCountry());
        self::assertTrue($address->isDefault());
    }

    public function testAUserWithoutPhoneNorAddressIsReadBackWithNone(): void
    {
        $id = DoctrineUserFactory::createOne()->getId();
        $this->entityManager->clear();

        $user = $this->repository->findById($id);

        self::assertNull($user->getPhoneNumber());
        self::assertSame([], $user->getAddresses());
    }

    public function testFindByIdThrowsWhenTheUserDoesNotExist(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository->findById(Uuid::v4()->toRfc4122());
    }

    public function testFindByEmail(): void
    {
        DoctrineUserFactory::createOne(['email' => 'ada@example.test', 'firstName' => 'Ada']);
        DoctrineUserFactory::createOne();
        $this->entityManager->clear();

        $user = $this->repository->findByEmail('ada@example.test');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('Ada', $user->getFirstName());
        self::assertNull($this->repository->findByEmail('nobody@example.test'));
    }

    public function testFindAllReturnsDomainUsers(): void
    {
        DoctrineUserFactory::createMany(2);
        DoctrineUserFactory::new()->admin()->create();
        $this->entityManager->clear();

        $users = $this->repository->findAll();

        // The classes are compared at runtime: the PHPDoc alone would not catch a Doctrine entity (bug B1).
        self::assertSame(array_fill(0, 3, User::class), array_map(static fn (object $item): string => $item::class, $users));
    }

    public function testSavingAnExistingUserUpdatesItAndKeepsItsAddresses(): void
    {
        $doctrineUser = DoctrineUserFactory::createOne(['firstName' => 'Ada', 'phoneNumber' => '+33612345678']);
        DoctrineAddressFactory::createOne(['user' => $doctrineUser, 'city' => 'Lyon']);
        $id = $doctrineUser->getId();
        $this->entityManager->clear();

        $user = $this->repository->findById($id);
        $user->updateProfile('Augusta', 'King');
        $user->updatePhoneNumber(new PhoneNumber('+33700000000'));
        $user->changePassword('new-hash');
        $this->repository->save($user);
        $this->entityManager->clear();

        $reloaded = $this->repository->findById($id);
        self::assertSame('Augusta', $reloaded->getFirstName());
        self::assertSame('King', $reloaded->getLastName());
        self::assertSame('+33700000000', $reloaded->getPhoneNumber()?->getValue());
        self::assertSame('new-hash', $reloaded->getPasswordHash());
        self::assertCount(1, $reloaded->getAddresses());
        self::assertSame('Lyon', $reloaded->getAddresses()[0]->getCity());
        self::assertCount(1, $this->repository->findAll());
    }

    public function testDeletingAUserAlsoDeletesItsAddresses(): void
    {
        $doctrineUser = DoctrineUserFactory::createOne();
        DoctrineAddressFactory::createMany(2, ['user' => $doctrineUser]);
        $other = DoctrineAddressFactory::createOne();
        $id = $doctrineUser->getId();
        $otherAddressId = $other->getId();
        $this->entityManager->clear();

        $this->repository->delete($id);
        $this->entityManager->clear();

        self::assertNull($this->repository->findByEmail($doctrineUser->getEmail()));
        $remaining = $this->entityManager->getConnection()->fetchFirstColumn('SELECT id FROM addresses');
        self::assertSame([$otherAddressId], $remaining);
    }

    public function testDeleteThrowsWhenTheUserDoesNotExist(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->repository->delete(Uuid::v4()->toRfc4122());
    }

    public function testTwoUsersCannotShareAnEmail(): void
    {
        DoctrineUserFactory::createOne(['email' => 'ada@example.test']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->repository->save(new User(Uuid::v4()->toRfc4122(), new Email('ada@example.test'), 'hash', 'Other', 'Ada'));
    }
}
