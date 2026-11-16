<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the 401 of the JWT firewall the error format of the rest of the API
 * ({"error": "..."}) instead of the one of the bundle ({"code": 401, "message": "..."}).
 */
final class JwtFailureListener
{
    public const string MISSING = 'Authentication is required.';
    public const string INVALID = 'The token is invalid.';
    public const string EXPIRED = 'The token has expired.';

    #[AsEventListener(event: Events::JWT_NOT_FOUND)]
    public function onMissing(AuthenticationFailureEvent $event): void
    {
        $event->setResponse(self::response(self::MISSING));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    public function onInvalid(AuthenticationFailureEvent $event): void
    {
        $event->setResponse(self::response(self::INVALID));
    }

    #[AsEventListener(event: Events::JWT_EXPIRED)]
    public function onExpired(AuthenticationFailureEvent $event): void
    {
        $event->setResponse(self::response(self::EXPIRED));
    }

    private static function response(string $message): JsonResponse
    {
        return new JsonResponse(['error' => $message], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
    }
}
