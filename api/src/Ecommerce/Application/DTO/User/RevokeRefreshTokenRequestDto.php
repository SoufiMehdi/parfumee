<?php

declare(strict_types=1);

namespace App\Ecommerce\Application\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RevokeRefreshTokenRequestDto
{
    public function __construct(
        /** The refresh token of the session to end, as the sign-in or the last refresh returned it. */
        #[Assert\NotBlank(normalizer: 'trim')]
        #[Assert\Length(max: 255)]
        public string $refreshToken,
    ) {
    }
}
