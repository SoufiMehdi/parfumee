<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Domain\Model\User;

use App\Ecommerce\Domain\Exception\User\UnknownRoleException;
use App\Ecommerce\Domain\Model\User\Role;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\ValueObject\User\Email;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private Stub&Email $emailMock;
    private string $id = 'user-123';
    private string $passwordHash = 'hashed_password';
    private string $firstName = 'John';
    private string $lastName = 'Doe';

    protected function setUp(): void
    {
        $this->emailMock = $this->createStub(Email::class);
    }

    public function testItShouldCreateUserSuccessfully(): void
    {
        $roles = ['ROLE_ADMIN'];
        $user = new User(
            $this->id,
            $this->emailMock,
            $this->passwordHash,
            $this->firstName,
            $this->lastName,
            null,
            $roles
        );

        $this->assertEquals($this->id, $user->getId());
        $this->assertSame($this->emailMock, $user->getEmail());
        $this->assertEquals($this->passwordHash, $user->getPasswordHash());
        $this->assertEquals($this->firstName, $user->getFirstName());
        $this->assertEquals($this->lastName, $user->getLastName());
        $this->assertEquals($roles, $user->getRoles());
    }

    public function testItShouldUseDefaultRolesWhenNoneProvided(): void
    {
        $user = new User(
            $this->id,
            $this->emailMock,
            $this->passwordHash,
            $this->firstName,
            $this->lastName
        );

        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    public function testItShouldUpdateProfileSuccessfully(): void
    {
        $user = new User(
            $this->id,
            $this->emailMock,
            $this->passwordHash,
            $this->firstName,
            $this->lastName
        );

        $newFirstName = 'Jane';
        $newLastName = 'Smith';

        $user->updateProfile($newFirstName, $newLastName);

        $this->assertEquals($newFirstName, $user->getFirstName());
        $this->assertEquals($newLastName, $user->getLastName());
    }

    public function testItShouldChangePasswordSuccessfully(): void
    {
        $user = new User(
            $this->id,
            $this->emailMock,
            $this->passwordHash,
            $this->firstName,
            $this->lastName
        );

        $newHash = 'new_secure_hash';
        $user->changePassword($newHash);

        $this->assertEquals($newHash, $user->getPasswordHash());
    }

    public function testGrantRoleAddsTheRoleOnce(): void
    {
        $user = new User($this->id, $this->emailMock, $this->passwordHash, $this->firstName, $this->lastName);

        $user->grantRole(Role::EDITOR);
        $user->grantRole(Role::EDITOR);

        $this->assertSame([Role::USER, Role::EDITOR], $user->getRoles());
    }

    public function testGrantRoleRefusesAnUnknownRole(): void
    {
        $user = new User($this->id, $this->emailMock, $this->passwordHash, $this->firstName, $this->lastName);

        $this->expectException(UnknownRoleException::class);

        try {
            $user->grantRole('ROLE_SUPER_ADMIN');
        } finally {
            $this->assertSame([Role::USER], $user->getRoles());
        }
    }
}
