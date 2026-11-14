<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\RegisterAddressDto;
use App\Ecommerce\Application\DTO\User\RegisterUserRequestDto;
use App\Ecommerce\Application\UseCase\User\RegisterUserUseCase;
use App\Ecommerce\Domain\Exception\User\DuplicateEmailException;
use App\Ecommerce\Domain\Model\User\Address;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Service\User\PasswordHasherInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class RegisterUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface|MockObject $userRepository;
    private PasswordHasherInterface|MockObject $passwordHasher;
    private RegisterUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(PasswordHasherInterface::class);

        $this->useCase = new RegisterUserUseCase(
            $this->userRepository,
            $this->passwordHasher
        );
    }

    public function testExecuteSuccessfullyRegistersUser(): void
    {
        $dto = new RegisterUserRequestDto(
            email: 'test@example.com',
            password: 'plainPassword',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->userRepository
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn(null);

        $this->passwordHasher
            ->expects($this->once())
            ->method('hash')
            ->with('plainPassword')
            ->willReturn('hashedPassword');

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user) use ($dto) {
                return $user->getEmail()->getValue() === $dto->email &&
                       $user->getFirstName() === $dto->firstName;
            }));

        $this->useCase->execute($dto);
    }

    public function testExecuteThrowsExceptionWhenEmailAlreadyExists(): void
    {
        $dto = new RegisterUserRequestDto(
            email: 'existing@example.com',
            password: 'plainPassword',
            firstName: 'John',
            lastName: 'Doe'
        );

        $existingUser = $this->createMock(User::class);

        $this->userRepository
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn($existingUser);

        $this->passwordHasher->expects($this->never())->method('hash');
        $this->userRepository->expects($this->never())->method('save');

        $this->expectException(DuplicateEmailException::class);
        $this->expectExceptionMessage('already taken');

        $this->useCase->execute($dto);
    }

    public function testExecuteThrowsExceptionWhenHasherFails(): void
    {
        $dto = new RegisterUserRequestDto(
            email: 'test@example.com',
            password: 'plainPassword',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->userRepository
            ->method('findByEmail')
            ->willReturn(null);

        $this->passwordHasher
            ->method('hash')
            ->willThrowException(new \RuntimeException('Hashing failed'));

        $this->userRepository->expects($this->never())->method('save');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Hashing failed');

        $this->useCase->execute($dto);
    }

    public function testExecuteThrowsExceptionWhenRepositoryFails(): void
    {
        $dto = new RegisterUserRequestDto(
            email: 'test@example.com',
            password: 'plainPassword',
            firstName: 'John',
            lastName: 'Doe'
        );

        $this->userRepository
            ->method('findByEmail')
            ->willReturn(null);

        $this->passwordHasher->method('hash')->willReturn('hashedPassword');

        $this->userRepository
            ->method('save')
            ->willThrowException(new \RuntimeException('Database error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        $this->useCase->execute($dto);
    }

    public function testExecuteKeepsThePhoneNumberAndTheAddress(): void
    {
        $dto = new RegisterUserRequestDto(
            email: 'test@example.com',
            password: 'plainPassword',
            firstName: 'John',
            lastName: 'Doe',
            phoneNumber: '+33 6 12 34 56 78',
            address: new RegisterAddressDto('1 rue de la Paix', 'Paris', '75002', 'France', Address::TYPE_BILLING),
        );
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->passwordHasher->method('hash')->willReturn('hashedPassword');

        $saved = null;
        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (User $user) use (&$saved): void {
                $saved = $user;
            });

        $this->useCase->execute($dto);

        $this->assertInstanceOf(User::class, $saved);
        $this->assertSame('+33 6 12 34 56 78', $saved->getPhoneNumber()?->getValue());
        $this->assertCount(1, $saved->getAddresses());
        $address = $saved->getAddresses()[0];
        $this->assertSame('1 rue de la Paix', $address->getStreet());
        $this->assertSame('Paris', $address->getCity());
        $this->assertSame('75002', $address->getPostalCode());
        $this->assertSame('France', $address->getCountry());
        $this->assertSame(Address::TYPE_BILLING, $address->getType());
        $this->assertTrue($address->isDefault());
    }

    public function testExecuteWithoutPhoneNorAddressLeavesThemEmpty(): void
    {
        $dto = new RegisterUserRequestDto('test@example.com', 'plainPassword', 'John', 'Doe');
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->passwordHasher->method('hash')->willReturn('hashedPassword');

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(static fn (User $user): bool => null === $user->getPhoneNumber() && [] === $user->getAddresses()));

        $this->useCase->execute($dto);
    }
}
