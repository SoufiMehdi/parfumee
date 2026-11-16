<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Service\User;

use App\Ecommerce\Domain\Service\User\RefreshTokenIssuerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

class GesdinetRefreshTokenIssuer implements RefreshTokenIssuerInterface
{
    public function __construct(
        private RefreshTokenGeneratorInterface $generator,
        private RefreshTokenManagerInterface $manager,
        private int $ttl,
    ) {
    }

    public function issue(string $userIdentifier): string
    {
        // The bundle only reads the identifier of the user it is given.
        $refreshToken = $this->generator->createForUserWithTtl(new InMemoryUser($userIdentifier, null), $this->ttl);

        // Read before saving: tokens are stored hashed, and saving replaces the value by its hash.
        $value = (string) $refreshToken->getRefreshToken();

        $this->manager->save($refreshToken);

        return $value;
    }
}
