<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDTO;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Service\User\PasswordVerifierInterface;
use App\Ecommerce\Domain\Service\User\TokenGeneratorInterface;
use App\Ecommerce\Domain\ValueObject\User\Email;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class LoginUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordVerifierInterface&MockObject $passwordVerifier;
    private TokenGeneratorInterface&MockObject $tokenGenerator;
    private LoginUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordVerifier = $this->createMock(PasswordVerifierInterface::class);
        $this->tokenGenerator = $this->createMock(TokenGeneratorInterface::class);

        $this->useCase = new LoginUserUseCase(
            $this->userRepository,
            $this->passwordVerifier,
            $this->tokenGenerator,
        );
    }

    public function testExecuteReturnsTokenOnSuccess(): void
    {
        $dto = new LoginUserRequestDTO(email: 'user@example.com', password: 'plainPassword');

        $user = new User(
            'test-uuid',
            new Email('user@example.com'),
            'hashedPassword',
            'John',
            'Doe',
        );

        $this->userRepository
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn($user);

        $this->passwordVerifier
            ->method('verify')
            ->with('user@example.com', 'plainPassword')
            ->willReturn(true);

        $this->tokenGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('user@example.com', ['ROLE_USER'])
            ->willReturn('jwt-token-abc');

        $result = $this->useCase->execute($dto);

        $this->assertSame(['token' => 'jwt-token-abc'], $result);
    }

    public function testExecuteThrowsExceptionWhenUserNotFound(): void
    {
        $dto = new LoginUserRequestDTO(email: 'unknown@example.com', password: 'password');

        $this->userRepository
            ->method('findByEmail')
            ->willReturn(null);

        $this->passwordVerifier->expects($this->never())->method('verify');
        $this->tokenGenerator->expects($this->never())->method('generate');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->useCase->execute($dto);
    }

    public function testExecuteThrowsExceptionWhenPasswordInvalid(): void
    {
        $dto = new LoginUserRequestDTO(email: 'user@example.com', password: 'wrongPassword');

        $user = new User(
            'test-uuid',
            new Email('user@example.com'),
            'hashedPassword',
            'John',
            'Doe',
        );

        $this->userRepository
            ->method('findByEmail')
            ->willReturn($user);

        $this->passwordVerifier
            ->method('verify')
            ->willReturn(false);

        $this->tokenGenerator->expects($this->never())->method('generate');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->useCase->execute($dto);
    }
}
