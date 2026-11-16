<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Admin;

use App\Ecommerce\Application\DTO\User\LoginUserRequestDto;
use App\Ecommerce\Application\UseCase\User\LoginUserUseCase;
use App\Ecommerce\Infrastructure\Presenter\User\View\SessionView;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sign-in of the back-office. It issues the same token as the shop sign-in:
 * what an account may do is decided on every admin route, from its roles.
 */
#[Route('/admin', name: 'admin_')]
#[OA\Tag(name: 'Back-office · Authentication')]
class AuthController extends AbstractController
{
    #[Route('/login', name: 'login', methods: [Request::METHOD_POST])]
    #[Security(name: null)]
    #[OA\Post(summary: 'Sign in to the back-office', description: 'Any account can sign in; the routes of the back-office then refuse an account without the editor or administrator role.')]
    #[OA\Response(response: 200, description: 'The session', content: new Model(type: SessionView::class))]
    #[OA\Response(response: 400, ref: '#/components/responses/BadRequest')]
    #[OA\Response(response: 401, description: 'Wrong email or password', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[OA\Response(response: 422, ref: '#/components/responses/ValidationFailed')]
    public function login(#[MapRequestPayload] LoginUserRequestDto $dto, LoginUserUseCase $useCase): JsonResponse
    {
        $session = $useCase->execute($dto);

        return $this->json(new SessionView($session['token'], $session['refresh_token']));
    }
}
