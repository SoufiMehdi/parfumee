<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Service\User;

use App\Ecommerce\Domain\Service\User\TokenGeneratorInterface;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class JwtTokenGenerator implements TokenGeneratorInterface
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    /**
     * @param string[] $roles
     */
    public function generate(string $userIdentifier, array $roles): string
    {
        $doctrineUser = new DoctrineUser(
            id: '00000000-0000-0000-0000-000000000000',
            email: $userIdentifier,
            password: '',
            firstName: '',
            lastName: '',
        );
        $doctrineUser->setRoles($roles);

        return $this->jwtManager->create($doctrineUser);
    }
}
