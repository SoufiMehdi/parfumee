<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Presenter\User;

use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\ValueObject\User\Email;
use App\Ecommerce\Domain\ValueObject\User\PhoneNumber;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
use PHPUnit\Framework\TestCase;

class UserPresenterTest extends TestCase
{
    private UserPresenter $presenter;

    protected function setUp(): void
    {
        $this->presenter = new UserPresenter();
    }

    public function testPresentReturnsFormattedUser(): void
    {
        $email = new Email('john@example.com');
        $phone = new PhoneNumber('+33612345678');

        $user = new User(
            'test-uuid-123',
            $email,
            'hashed-password',
            'John',
            'Doe',
            $phone,
            ['ROLE_USER']
        );

        $result = $this->presenter->present($user);

        $this->assertSame('test-uuid-123', $result['id']);
        $this->assertSame('john@example.com', $result['email']);
        $this->assertSame('John', $result['firstName']);
        $this->assertSame('Doe', $result['lastName']);
        $this->assertSame('+33612345678', $result['phoneNumber']);
        $this->assertSame(['ROLE_USER'], $result['roles']);
        $this->assertIsArray($result['addresses']);
    }

    public function testPresentHandlesNullPhoneNumber(): void
    {
        $email = new Email('jane@example.com');

        $user = new User(
            'test-uuid-456',
            $email,
            'hashed-password',
            'Jane',
            'Doe',
            null,
            ['ROLE_USER']
        );

        $result = $this->presenter->present($user);

        $this->assertNull($result['phoneNumber']);
    }

    public function testPresentCollectionReturnsArrayOfFormattedUsers(): void
    {
        $email = new Email('user@example.com');
        $user = new User(
            'test-uuid-789',
            $email,
            'hashed-password',
            'Test',
            'User',
            null,
            ['ROLE_USER']
        );

        $result = $this->presenter->presentCollection([$user]);

        $this->assertCount(1, $result);
        $this->assertSame('Test', $result[0]['firstName']);
    }

    public function testPresentCollectionWithAddresses(): void
    {
        $email = new Email('user@example.com');
        $address = new Address(
            'addr-uuid-1',
            'shipping',
            '123 Main St',
            'Paris',
            '75001',
            'France',
            true
        );

        $user = new User(
            'test-uuid-999',
            $email,
            'hashed-password',
            'User',
            'WithAddress',
            null,
            ['ROLE_USER'],
            [$address]
        );

        $result = $this->presenter->present($user);

        $this->assertCount(1, $result['addresses']);
        $this->assertSame('shipping', $result['addresses'][0]['type']);
        $this->assertSame('123 Main St', $result['addresses'][0]['street']);
        $this->assertTrue($result['addresses'][0]['isDefault']);
    }
}
