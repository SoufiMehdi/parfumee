<?php

declare(strict_types=1);

namespace App\Tests\Unit\Ecommerce\Infrastructure\Persistence\Doctrine\User;

use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Infrastructure\Persistence\Doctrine\User\DoctrineUserRepository;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use App\Ecommerce\Infrastructure\Persistence\Mapper\User\UserMapper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class DoctrineUserRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserMapper&MockObject $mapper;
    private DoctrineUserRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mapper = $this->createMock(UserMapper::class);
        $this->repository = new DoctrineUserRepository($this->entityManager, $this->mapper);
    }

    public function testFindByIdReturnsUser(): void
    {
        $id = Uuid::v4();
        $doctrineUser = $this->createStub(DoctrineUser::class);
        $domainUser = $this->createStub(User::class);

        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(DoctrineUser::class, $id->toRfc4122())
            ->willReturn($doctrineUser);

        $this->mapper
            ->expects($this->once())
            ->method('toDomain')
            ->with($doctrineUser)
            ->willReturn($domainUser);

        $result = $this->repository->findById($id);

        $this->assertSame($domainUser, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFindByIdThrowsExceptionWhenNotFound(): void
    {
        $id = Uuid::v4();

        $this->entityManager
            ->method('find')
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);

        $this->repository->findById($id);
    }

    public function testSaveCreatesNewUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(Uuid::v4()->toRfc4122());
        $doctrineUser = $this->createStub(DoctrineUser::class);

        // Simulate user not found (new user)
        $this->entityManager
            ->method('find')
            ->willReturn(null);

        $this->mapper
            ->expects($this->once())
            ->method('toInfrastructure')
            ->with($user)
            ->willReturn($doctrineUser);

        $this->entityManager->expects($this->once())->method('persist')->with($doctrineUser);
        $this->entityManager->expects($this->once())->method('flush');

        $this->repository->save($user);
    }

    public function testSaveUpdatesExistingUser(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(Uuid::v4()->toRfc4122());
        $existingDoctrineUser = $this->createStub(DoctrineUser::class);

        // Simulate user found (existing user)
        $this->entityManager
            ->method('find')
            ->willReturn($existingDoctrineUser);

        $this->mapper
            ->expects($this->once())
            ->method('mapDomainToEntity')
            ->with($user, $existingDoctrineUser);

        $this->entityManager->expects($this->once())->method('persist')->with($existingDoctrineUser);
        $this->entityManager->expects($this->once())->method('flush');

        $this->repository->save($user);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteRemovesAndFlushes(): void
    {
        $id = Uuid::v4();
        $doctrineUser = $this->createStub(DoctrineUser::class);

        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(DoctrineUser::class, $id->toRfc4122())
            ->willReturn($doctrineUser);

        $this->entityManager->expects($this->once())->method('remove')->with($doctrineUser);
        $this->entityManager->expects($this->once())->method('flush');

        $this->repository->delete($id);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testDeleteThrowsExceptionWhenUserNotFound(): void
    {
        $id = Uuid::v4();

        $this->entityManager
            ->method('find')
            ->willReturn(null);

        $this->expectException(UserNotFoundException::class);

        $this->repository->delete($id);
    }

    public function testFindByEmailReturnsUser(): void
    {
        $email = 'test@example.com';
        $doctrineUser = $this->createStub(DoctrineUser::class);
        $domainUser = $this->createStub(User::class);

        $entityRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(DoctrineUser::class)
            ->willReturn($entityRepository);

        $entityRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($doctrineUser);

        $this->mapper
            ->method('toDomain')
            ->with($doctrineUser)
            ->willReturn($domainUser);

        $result = $this->repository->findByEmail($email);

        $this->assertSame($domainUser, $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $email = 'notfound@example.com';

        $entityRepository = $this->createStub(\Doctrine\ORM\EntityRepository::class);
        $this->entityManager
            ->method('getRepository')
            ->willReturn($entityRepository);

        $entityRepository
            ->method('findOneBy')
            ->willReturn(null);

        $result = $this->repository->findByEmail($email);

        $this->assertNull($result);
    }
}
