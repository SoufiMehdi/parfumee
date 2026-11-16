<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Presenter\User;

use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\Model\User\Role;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
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

    public function testPresentCollectionKeepsTheOrderAndReturnsAList(): void
    {
        $users = [
            3 => new User('first', new Email('a@example.test'), 'hash', 'A', 'A'),
            7 => new User('second', new Email('b@example.test'), 'hash', 'B', 'B'),
        ];

        $views = $this->presenter->presentCollection($users);

        self::assertSame([0, 1], array_keys($views));
        self::assertSame(['first', 'second'], [$views[0]->id, $views[1]->id]);
    }
}
