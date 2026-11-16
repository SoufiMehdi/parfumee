<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Persistence\Entity\User;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshTokenRepository;

/**
 * Storage of the refresh tokens. The columns are mapped by the bundle; the value
 * column holds a hash of the token, never the token itself.
 *
 * It has no domain model: a refresh token is a technical detail of authentication.
 */
#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
class DoctrineRefreshToken extends RefreshToken
{
}
