<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Uid\Uuid;

class DeleteUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private DeleteUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new DeleteUserUseCase($this->userRepository);
    }

    public function testExecuteDeletesUser(): void
    {
        $id = Uuid::v4();

        $this->userRepository
            ->expects($this->once())
            ->method('delete')
            ->with($id);

        $this->useCase->execute($id->toRfc4122());
    }

    public function testExecuteThrowsExceptionWhenUserNotFound(): void
    {
        $id = Uuid::v4();

        $this->userRepository
            ->method('delete')
            ->willThrowException(new UserNotFoundException($id->toRfc4122()));

        $this->expectException(UserNotFoundException::class);

        $this->useCase->execute($id->toRfc4122());
    }
}
