<?php

declare(strict_types=1);

namespace App\Ecommerce\Domain\Repository\User;

use App\Ecommerce\Domain\Exception\User\UserNotFoundException;
use App\Ecommerce\Domain\Model\User\User;
use Symfony\Component\Uid\Uuid;

/**
 * Interface respectant le principe d'Interface Segregation (ISP).
 */
interface UserRepositoryInterface
{
    /**
     * @throws UserNotFoundException
     */
    public function findById(Uuid $id): User;

    /**
     * @return User[]
     */
    public function findAll(): array;

    public function findByEmail(string $email): ?User;

    public function save(User $user): void;

    public function delete(Uuid $id): void;
}