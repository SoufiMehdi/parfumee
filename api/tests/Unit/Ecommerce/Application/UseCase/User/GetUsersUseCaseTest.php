<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Application\UseCase\User;

use App\Ecommerce\Application\UseCase\User\GetUsersUseCase;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class GetUsersUseCaseTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private GetUsersUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->useCase = new GetUsersUseCase($this->userRepository);
    }

    public function testExecuteReturnsAllUsers(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);

        $this->userRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$user1, $user2]);

        $result = $this->useCase->execute();

        $this->assertCount(2, $result);
        $this->assertSame($user1, $result[0]);
        $this->assertSame($user2, $result[1]);
    }

    public function testExecuteReturnsEmptyArrayWhenNoUsers(): void
    {
        $this->userRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);

        $result = $this->useCase->execute();

        $this->assertEmpty($result);
    }
}
