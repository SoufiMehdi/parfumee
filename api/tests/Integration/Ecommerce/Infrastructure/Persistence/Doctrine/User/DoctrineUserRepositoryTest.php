<?php

declare(strict_types=1);

namespace App\Tests\Integration\Ecommerce\Infrastructure\Persistence\Doctrine\User;

use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\SortDirection;
use App\Ecommerce\Domain\Repository\User\UserCriteria;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Repository\User\UserSortField;
use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use App\Tests\Factory\User\DoctrineAddressFactory;
use App\Tests\Factory\User\DoctrineUserFactory;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

final class DoctrineUserRepositoryTest extends DatabaseTestCase
{
    use ClockSensitiveTrait;

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

    public function testAnAccountIsReadBackWithTheDatesOfItsSaves(): void
    {
        self::mockTime(new \DateTimeImmutable('2026-11-01T09:30:00+00:00'));
        $id = Uuid::v4()->toRfc4122();
        $this->repository->save(new User($id, new Email('ada@example.test'), 'hash', 'Ada', 'Lovelace'));
        $this->entityManager->clear();

        self::mockTime(new \DateTimeImmutable('2026-11-21T10:00:00+00:00'));
        $user = $this->repository->findById($id);
        $user->updateProfile('Augusta', 'King');
        $this->repository->save($user);

        // Read again right after the save, in the same request: what UpdateUserUseCase returns.
        $stored = $this->repository->findById($id);
        self::assertEquals(new \DateTimeImmutable('2026-11-01T09:30:00+00:00'), $stored->getCreatedAt());
        self::assertEquals(new \DateTimeImmutable('2026-11-21T10:00:00+00:00'), $stored->getUpdatedAt());
        // The object that was saved keeps the dates it was loaded with.
        self::assertEquals(new \DateTimeImmutable('2026-11-01T09:30:00+00:00'), $user->getUpdatedAt());
    }

    public function testSavingAnAccountWithoutAnyChangeWritesNothing(): void
    {
        $doctrineUser = DoctrineUserFactory::createOne(['phoneNumber' => '+33612345678']);
        DoctrineAddressFactory::createOne(['user' => $doctrineUser]);
        $this->entityManager->clear();
        $user = $this->repository->findById($doctrineUser->getId());

        $queries = $this->queriesRunBy(fn () => $this->repository->save($user));

        // An UPDATE would move updated_at: of the account, and of its addresses.
        self::assertSame([], $queries, implode("\n", $queries));
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

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function searches(): iterable
    {
        yield 'part of an email' => ['grace@', ['grace@example.test']];
        yield 'part of a first name, whatever the case' => ['ALA', ['alan@example.test']];
        yield 'part of a last name, whatever the case' => ['lovel', ['ada@example.test']];
        yield 'common to every account' => ['example', ['ada@example.test', 'alan@example.test', 'grace@example.test', 'under_score@example.test', 'underxscore@example.test']];
        // What is typed is searched as such: % and _ are not wildcards.
        yield 'an underscore is an underscore' => ['under_', ['under_score@example.test']];
        yield 'a percent sign is a percent sign' => ['%', []];
        yield 'nothing matches' => ['nobody', []];
    }

    /**
     * @param list<string> $expectedEmails
     */
    #[DataProvider('searches')]
    public function testFindPageSearchesTheEmailAndTheNames(string $search, array $expectedEmails): void
    {
        DoctrineUserFactory::createOne(['email' => 'ada@example.test', 'firstName' => 'Ada', 'lastName' => 'Lovelace']);
        DoctrineUserFactory::createOne(['email' => 'alan@example.test', 'firstName' => 'Alan', 'lastName' => 'Turing']);
        DoctrineUserFactory::createOne(['email' => 'grace@example.test', 'firstName' => 'Grace', 'lastName' => 'Hopper']);
        DoctrineUserFactory::createOne(['email' => 'under_score@example.test', 'firstName' => 'U', 'lastName' => 'S']);
        DoctrineUserFactory::createOne(['email' => 'underxscore@example.test', 'firstName' => 'U', 'lastName' => 'X']);
        $this->entityManager->clear();

        $page = $this->repository->findPage(new UserCriteria(search: $search), new Pagination());

        self::assertSame($expectedEmails, $this->emailsOf($page));
        self::assertSame(\count($expectedEmails), $page->total);
    }

    public function testLastNamesAreSortedAsAFrenchReaderExpects(): void
    {
        foreach (['Zola', 'Ébène', 'dupont', 'Durand'] as $lastName) {
            DoctrineUserFactory::createOne(['lastName' => $lastName]);
        }
        $this->entityManager->clear();

        $ascending = $this->repository->findPage(new UserCriteria(sortBy: UserSortField::LAST_NAME), new Pagination());
        $descending = $this->repository->findPage(new UserCriteria(sortBy: UserSortField::LAST_NAME, direction: SortDirection::DESC), new Pagination());

        $lastNames = static fn (Page $page): array => array_map(static fn (User $user): string => $user->getLastName(), $page->items);
        self::assertSame(['dupont', 'Durand', 'Ébène', 'Zola'], $lastNames($ascending));
        self::assertSame(['Zola', 'Ébène', 'Durand', 'dupont'], $lastNames($descending));
    }

    public function testFindPageReturnsOneSliceSortedByEmailAndTheTotal(): void
    {
        foreach (['e', 'c', 'a', 'd', 'b'] as $letter) {
            DoctrineUserFactory::createOne(['email' => $letter.'@example.test']);
        }
        $this->entityManager->clear();

        $second = $this->repository->findPage(new UserCriteria(), new Pagination(page: 2, perPage: 2));
        $afterTheLast = $this->repository->findPage(new UserCriteria(), new Pagination(page: 4, perPage: 2));

        self::assertSame(['c@example.test', 'd@example.test'], $this->emailsOf($second));
        self::assertSame([], $afterTheLast->items);
        self::assertSame(5, $second->total);
        self::assertSame(5, $afterTheLast->total);
        self::assertSame(3, $second->totalPages());
    }

    public function testAccountsCanBeSortedByCreationDate(): void
    {
        foreach (['first' => '2026-11-01', 'third' => '2026-11-03', 'second' => '2026-11-02'] as $name => $day) {
            self::mockTime(new \DateTimeImmutable($day.'T09:30:00+00:00'));
            DoctrineUserFactory::createOne(['email' => $name.'@example.test']);
        }
        $this->entityManager->clear();

        $oldestFirst = $this->repository->findPage(new UserCriteria(sortBy: UserSortField::CREATED_AT), new Pagination());
        $newestFirst = $this->repository->findPage(new UserCriteria(sortBy: UserSortField::CREATED_AT, direction: SortDirection::DESC), new Pagination());

        self::assertSame(['first@example.test', 'second@example.test', 'third@example.test'], $this->emailsOf($oldestFirst));
        self::assertSame(['third@example.test', 'second@example.test', 'first@example.test'], $this->emailsOf($newestFirst));
    }

    public function testAPageIsLoadedWithTheAddressesInThreeQueries(): void
    {
        foreach (range(1, 4) as $i) {
            DoctrineAddressFactory::createMany(2, ['user' => DoctrineUserFactory::createOne()]);
        }
        $this->entityManager->clear();

        $page = null;
        $queries = $this->queriesRunBy(function () use (&$page): void {
            $page = $this->repository->findPage(new UserCriteria(), new Pagination());
        });

        self::assertCount(3, $queries, implode("\n", $queries));
        self::assertInstanceOf(Page::class, $page);
        self::assertCount(4, $page->items);
        foreach ($page->items as $user) {
            self::assertCount(2, $user->getAddresses());
        }
    }

    /**
     * @param Page<User> $page
     *
     * @return list<string>
     */
    private function emailsOf(Page $page): array
    {
        return array_map(static fn (User $user): string => $user->getEmail()->getValue(), $page->items);
    }
}
