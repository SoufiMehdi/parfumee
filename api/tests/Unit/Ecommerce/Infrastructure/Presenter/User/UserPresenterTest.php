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
        ), $view);
    }

    public function testTheViewHasNoPlaceForThePassword(): void
    {
        $user = new User('user-id', new Email('ada@example.test'), 'hashed-password', 'Ada', 'Lovelace');

        $view = $this->presenter->present($user);

        self::assertNotContains('hashed-password', get_object_vars($view));
    }

    public function testAUserWithoutPhoneNorAddressIsPresentedWithNone(): void
    {
        $user = new User('user-id', new Email('ada@example.test'), 'hashed-password', 'Ada', 'Lovelace');

        $view = $this->presenter->present($user);

        self::assertNull($view->phoneNumber);
        self::assertSame([], $view->addresses);
        self::assertSame([Role::USER], $view->roles);
    }

    public function testPresentPageKeepsTheOrderOfTheItemsAndDescribesThePage(): void
    {
        $page = new Page(
            [
                new User('first', new Email('a@example.test'), 'hash', 'A', 'A'),
                new User('second', new Email('b@example.test'), 'hash', 'B', 'B'),
            ],
            total: 2,
            pagination: new Pagination(),
        );

        $list = $this->presenter->presentPage($page);

        self::assertSame(['first', 'second'], [$list->items[0]->id, $list->items[1]->id]);
        self::assertEquals(new PaginationView(page: 1, perPage: Pagination::DEFAULT_PER_PAGE, total: 2, totalPages: 1), $list->pagination);
    }
}
