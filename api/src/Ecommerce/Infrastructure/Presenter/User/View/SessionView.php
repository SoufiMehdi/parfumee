<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Presenter\User\View;

/**
 * What a sign-in or a token refresh returns.
 */
final readonly class SessionView
{
    public function __construct(
        /** JWT to send in the Authorization header. It lasts 15 minutes. */
        public string $token,
        /** Single-use value to exchange for a new session on /api/v1/token/refresh. */
        public string $refreshToken,
    ) {
    }
}
