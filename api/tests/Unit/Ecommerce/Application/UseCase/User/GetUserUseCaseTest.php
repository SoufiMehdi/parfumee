<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class GetUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private GetUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new GetUserUseCase($this->userRepository);
    }

    public function testExecuteReturnsUser(): void
    {
        $id = Uuid::v4()->toRfc4122();
        $user = $this->createStub(User::class);

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($user);

        $result = $this->useCase->execute($id);

        $this->assertSame($user, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteThrowsExceptionWhenUserNotFound(): void
    {
        $id = Uuid::v4()->toRfc4122();

        $this->userRepository
            ->method('findById')
            ->willThrowException(new UserNotFoundException($id));

        $this->expectException(UserNotFoundException::class);

        $this->useCase->execute($id);
    }
}
