<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Admin;

use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
use App\Ecommerce\Infrastructure\Presenter\User\View\UserView;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Who is signed in to the back-office, and with which roles: what the
 * back-office needs to show the identity and to hide what the account may not do.
 */
#[Route('/admin/me', name: 'admin_me_')]
#[OA\Tag(name: 'Back-office · Authentication')]
class MeController extends AbstractController
{
    #[Route('', name: 'get', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Read my identity and my roles', description: 'Requires the editor role.')]
    #[OA\Response(response: 200, description: 'The account of the token', content: new Model(type: UserView::class))]
    #[OA\Response(response: 401, ref: '#/components/responses/Unauthorized')]
    #[OA\Response(response: 403, ref: '#/components/responses/Forbidden')]
    public function get(#[CurrentUser] DoctrineUser $user, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return $this->json($presenter->present($useCase->execute($user->getId())));
    }
}
