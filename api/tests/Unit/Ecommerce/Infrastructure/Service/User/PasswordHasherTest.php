<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Service\User;

use App\Ecommerce\Infrastructure\Service\User\PasswordHasher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

class PasswordHasherTest extends TestCase
{
    private UserPasswordHasherInterface $symfonyHasher;
    private PasswordHasher $passwordHasher;

    protected function setUp(): void
    {
        $this->symfonyHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->passwordHasher = new PasswordHasher($this->symfonyHasher);
    }

    public function testHashSuccessfullyReturnsHashedPassword(): void
    {
        $plainPassword = 'plainPassword123';
        $hashedPassword = 'hashedPassword123';

        $this->symfonyHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with(
                $this->isInstanceOf(PasswordAuthenticatedUserInterface::class),
                $plainPassword
            )
            ->willReturn($hashedPassword);

        $result = $this->passwordHasher->hash($plainPassword);

        $this->assertEquals($hashedPassword, $result);
    }

    public function testHashThrowsExceptionWhenSymfonyHasherFails(): void
    {
        $plainPassword = 'plainPassword123';

        $this->symfonyHasher
            ->method('hashPassword')
            ->willThrowException(new \RuntimeException('Hashing failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hashing failed');

        $this->passwordHasher->hash($plainPassword);
    }
}
