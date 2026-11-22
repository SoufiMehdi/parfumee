<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Doctrine\User;

use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Model\User\User;
use App\Ecommerce\Domain\Repository\Page;
use App\Ecommerce\Domain\Repository\Pagination;
use App\Ecommerce\Domain\Repository\User\UserCriteria;
use App\Ecommerce\Domain\Repository\User\UserRepositoryInterface;
use App\Ecommerce\Domain\Repository\User\UserSortField;
use App\Ecommerce\Infrastructure\Persistence\Doctrine\SqlOrderBy;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use App\Ecommerce\Infrastructure\Persistence\Mapper\User\UserMapper;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineUserRepository implements UserRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserMapper $mapper,
    ) {
    }

    public function findById(string $id): User
    {
        $doctrineUser = $this->entityManager->find(DoctrineUser::class, $id);

        if (!$doctrineUser) {
            throw new UserNotFoundException($id);
        }

        return $this->mapper->toDomain($doctrineUser);
    }

    public function findAll(): array
    {
        $doctrineUsers = $this->entityManager->getRepository(DoctrineUser::class)->findAll();

        return array_map([$this->mapper, 'toDomain'], $doctrineUsers);
    }

    /**
     * @return Page<User>
     */
    public function findPage(UserCriteria $criteria, Pagination $pagination): Page
    {
        // SQL rather than DQL for the French sort, which DQL cannot express:
        // the ids of the page are found in SQL, then loaded in DQL.
        $where = '';
        $parameters = [];
        if (null !== $criteria->search) {
            $where = ' WHERE (u.email ILIKE :search OR u.first_name ILIKE :search OR u.last_name ILIKE :search)';
            // What the user typed is searched as such: % and _ are not wildcards.
            $parameters['search'] = '%'.addcslashes($criteria->search, '\\%_').'%';
        }

        $sortColumn = match ($criteria->sortBy) {
            UserSortField::EMAIL => SqlOrderBy::frenchText('u.email'),
            UserSortField::FIRST_NAME => SqlOrderBy::frenchText('u.first_name'),
            UserSortField::LAST_NAME => SqlOrderBy::frenchText('u.last_name'),
            UserSortField::CREATED_AT => 'u.created_at',
        };

        $connection = $this->entityManager->getConnection();
        $total = (int) $connection->fetchOne('SELECT COUNT(*) FROM users u'.$where, $parameters);
        $ids = array_map(strval(...), $connection->fetchFirstColumn(
            'SELECT u.id FROM users u'.$where
                .' ORDER BY '.SqlOrderBy::clause($sortColumn, $criteria->direction, 'u.id')
                .' LIMIT :limit OFFSET :offset',
            $parameters + ['limit' => $pagination->perPage, 'offset' => $pagination->offset()],
            ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER],
        ));

        return new Page($this->loadInOrder($ids), $total, $pagination);
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

    public function delete(string $id): void
    {
        $doctrineUser = $this->entityManager->find(DoctrineUser::class, $id);

        if (!$doctrineUser) {
            throw new UserNotFoundException($id);
        }

        $this->entityManager->remove($doctrineUser);
        $this->entityManager->flush();
    }

    /**
     * Loads the accounts of a page, with their addresses, in one query, and keeps the order of the ids.
     *
     * @param list<string> $ids
     *
     * @return list<User>
     */
    private function loadInOrder(array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        $entities = $this->entityManager
            ->createQuery('SELECT u, a FROM '.DoctrineUser::class.' u LEFT JOIN u.addresses a WHERE u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getResult();

        $byId = [];
        foreach ($entities as $entity) {
            if ($entity instanceof DoctrineUser) {
                $byId[$entity->getId()] = $entity;
            }
        }

        $users = [];
        foreach ($ids as $id) {
            // An account deleted between the two queries is left out of the page.
            if (isset($byId[$id])) {
                $users[] = $this->mapper->toDomain($byId[$id]);
            }
        }

        return $users;
    }
}
