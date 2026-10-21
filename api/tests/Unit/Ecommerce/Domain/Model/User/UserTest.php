<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Domain\Model\User;

use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\ValueObject\User\Email;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class UserTest extends TestCase
{
    private MockObject&Email $emailMock;
    private string $id = 'user-123';
    private string $passwordHash = 'hashed_password';
    private string $firstName = 'John';
    private string $lastName = 'Doe';

    protected function setUp(): void
    {
        $this->emailMock = $this->createMock(Email::class);
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
}