<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\DTO\User\UpdateUserRequestDTO;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Uid\Uuid;

class UpdateUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private UpdateUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new UpdateUserUseCase($this->userRepository);
    }

    public function testExecuteUpdatesProfile(): void
    {
        $id = Uuid::v4();
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id->toRfc4122());

        $dto = new UpdateUserRequestDTO(
            firstName: 'Jane',
            lastName: 'Smith',
            phoneNumber: null,
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($user);

        $user->expects($this->once())->method('updateProfile')->with('Jane', 'Smith');
        $user->expects($this->never())->method('updatePhoneNumber');

        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($user);

        $result = $this->useCase->execute($id->toRfc4122(), $dto);

        $this->assertSame($user, $result);
    }

    public function testExecuteUpdatesPhoneNumber(): void
    {
        $id = Uuid::v4();
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id->toRfc4122());

        $dto = new UpdateUserRequestDTO(
            firstName: 'Jane',
            lastName: 'Smith',
            phoneNumber: '+33698765432',
        );

        $this->userRepository
            ->method('findById')
            ->willReturn($user);

        $user->expects($this->once())->method('updateProfile')->with('Jane', 'Smith');
        $user->expects($this->once())->method('updatePhoneNumber');

        $this->userRepository->method('save');

        $this->useCase->execute($id->toRfc4122(), $dto);
    }

    public function testExecuteThrowsExceptionWhenUserNotFound(): void
    {
        $id = Uuid::v4();
        $dto = new UpdateUserRequestDTO(firstName: 'Jane', lastName: 'Smith');

        $this->userRepository
            ->method('findById')
            ->willThrowException(new UserNotFoundException($id->toRfc4122()));

        $this->expectException(UserNotFoundException::class);

        $this->useCase->execute($id->toRfc4122(), $dto);
    }
}
