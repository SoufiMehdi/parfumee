<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Doctrine\User;

use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Model\User\User;
use Symfony\Component\Uid\Uuid;
use Doctrine\ORM\EntityManagerInterface;
use App\Ecommerce\Infrastructure\Persistence\Mapper\User\UserMapper;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;

class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserMapper $mapper
    ) {
    }

    public function findById(Uuid $id): User
    {
        $doctrineUser = $this->entityManager->find(DoctrineUser::class, $id->toRfc4122());

        if (!$doctrineUser) {
            throw new \App\Ecommerce\Domain\Exception\User\UserNotFoundException($id->toRfc4122());
        }

        return $this->mapper->toDomain($doctrineUser);
    }

    public function findAll(): array
    {
        $doctrineUsers = $this->entityManager->getRepository(DoctrineUser::class)->findAll();

        return array_map([$this->mapper, 'toDomain'], $doctrineUsers);
    }

    public function findByEmail(string $email): ?User
    {
        $doctrineUser = $this->entityManager->getRepository(DoctrineUser::class)
            ->findOneBy(['email' => $email]);

        return $doctrineUser ? $this->mapper->toDomain($doctrineUser) : null;
    }

    public function save(User $user): void
    {
        $doctrineUser = $this->entityManager->find(DoctrineUser::class, $user->getId());

        if (!$doctrineUser) {
            // Nouvel utilisateur : mapper et persist
            $doctrineUser = $this->mapper->toInfrastructure($user);
        } else {
            // Utilisateur existant : mettre à jour l'entité
            $this->mapper->mapDomainToEntity($user, $doctrineUser);
        }

        $this->entityManager->persist($doctrineUser);
        $this->entityManager->flush();
    }

    public function delete(Uuid $id): void
    {
        $doctrineUser = $this->entityManager->find(DoctrineUser::class, $id->toRfc4122());

        if (!$doctrineUser) {
            throw new \App\Ecommerce\Domain\Exception\User\UserNotFoundException($id->toRfc4122());
        }

        $this->entityManager->remove($doctrineUser);
        $this->entityManager->flush();
    }
}