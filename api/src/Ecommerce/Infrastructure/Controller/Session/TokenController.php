<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Session;

use App\Ecommerce\Application\DTO\User\RevokeRefreshTokenRequestDto;
use App\Ecommerce\Application\UseCase\User\RevokeRefreshTokenUseCase;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The sessions, shared by both fronts. The token refresh, next to it, has no
 * controller: the refresh_jwt authenticator of the firewall answers it.
 */
#[Route('/token', name: 'api_token_')]
#[OA\Tag(name: 'Sessions')]
#[Security(name: null)]
class TokenController extends AbstractController
{
    #[Route('/revoke', name: 'revoke', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Sign out', description: 'The refresh token can no longer be exchanged for a new session. The JWT already issued stays valid until it expires (15 minutes): a front forgets it. An unknown or already revoked refresh token is accepted too, so that signing out never fails.')]
    #[OA\Response(response: 204, description: 'The refresh token is revoked')]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function revoke(#[MapRequestPayload] RevokeRefreshTokenRequestDto $dto, RevokeRefreshTokenUseCase $useCase): Response
    {
        $useCase->execute($dto);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
