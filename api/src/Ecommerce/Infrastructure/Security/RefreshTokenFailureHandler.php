<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Answers a refused token refresh in the error format of the API. The reason
 * (unknown, expired, already used) is not told: it would only help an attacker.
 */
final class RefreshTokenFailureHandler implements AuthenticationFailureHandlerInterface
{
    public const string MESSAGE = 'The refresh token is invalid.';

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(['error' => self::MESSAGE], Response::HTTP_UNAUTHORIZED);
    }
}
