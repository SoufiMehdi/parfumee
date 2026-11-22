<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Presenter\User;

use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\Model\User\Role;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use App\Ecommerce\Infrastructure\Presenter\Common\PaginationView;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
use App\Ecommerce\Infrastructure\Presenter\User\View\AddressView;
use App\Ecommerce\Infrastructure\Presenter\User\View\UserView;
use PHPUnit\Framework\TestCase;

final class UserPresenterTest extends TestCase
{
    // An account handed to the presenter has always been saved: it has its dates.
    private const string CREATED_AT = '2026-11-01T09:30:00+00:00';
    private const string UPDATED_AT = '2026-11-20T18:00:00+00:00';

    private UserPresenter $presenter;

    protected function setUp(): void
    {
        $this->presenter = new UserPresenter();
    }

    public function testPresentCopiesEveryFieldOfTheUser(): void
    {
        $user = new User(
            'user-id',
            new Email('ada@example.test'),
            'hashed-password',
            'Ada',
            'Lovelace',
            new PhoneNumber('+33612345678'),
            [Role::USER, Role::ADMIN],
            [new Address('address-id', Address::TYPE_BILLING, '1 rue des Bougies', 'Lyon', '69001', 'FR', true)],
            new \DateTimeImmutable(self::CREATED_AT),
            new \DateTimeImmutable(self::UPDATED_AT),
        );

        $view = $this->presenter->present($user);

        self::assertEquals(new UserView(
            'user-id',
            'ada@example.test',
            'Ada',
            'Lovelace',
            '+33612345678',
            [Role::USER, Role::ADMIN],
            [new AddressView('address-id', Address::TYPE_BILLING, '1 rue des Bougies', 'Lyon', '69001', 'FR', true)],
            new \DateTimeImmutable(self::CREATED_AT),
            new \DateTimeImmutable(self::UPDATED_AT),
        ), $view);
    }

    public function testTheViewHasNoPlaceForThePassword(): void
    {
        $user = self::savedUser('user-id', 'ada@example.test');

        $view = $this->presenter->present($user);

        self::assertNotContains('hashed-password', get_object_vars($view));
    }

    public function testAUserWithoutPhoneNorAddressIsPresentedWithNone(): void
    {
        $user = self::savedUser('user-id', 'ada@example.test');

        $view = $this->presenter->present($user);

        self::assertNull($view->phoneNumber);
        self::assertSame([], $view->addresses);
        self::assertSame([Role::USER], $view->roles);
    }

    public function testAnAccountNeverSavedCannotBePresented(): void
    {
        // Without dates: a use case returned it before saving it, which is a bug (500).
        $user = new User('user-id', new Email('ada@example.test'), 'hashed-password', 'Ada', 'Lovelace');

        $this->expectException(\LogicException::class);

        $this->presenter->present($user);
    }

    public function testPresentPageKeepsTheOrderOfTheItemsAndDescribesThePage(): void
    {
        $page = new Page(
            [
                self::savedUser('first', 'a@example.test'),
                self::savedUser('second', 'b@example.test'),
            ],
            total: 2,
            pagination: new Pagination(),
        );

        $list = $this->presenter->presentPage($page);

        self::assertSame(['first', 'second'], [$list->items[0]->id, $list->items[1]->id]);
        self::assertEquals(new PaginationView(page: 1, perPage: Pagination::DEFAULT_PER_PAGE, total: 2, totalPages: 1), $list->pagination);
    }

    private static function savedUser(string $id, string $email): User
    {
        return new User(
            $id,
            new Email($email),
            'hashed-password',
            'Ada',
            'Lovelace',
            createdAt: new \DateTimeImmutable(self::CREATED_AT),
            updatedAt: new \DateTimeImmutable(self::UPDATED_AT),
        );
    }
}
