<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Admin;

use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
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
class MeController extends AbstractController
{
    #[Route('', name: 'get', methods: [Request::METHOD_GET])]
    public function get(#[CurrentUser] DoctrineUser $user, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($useCase->execute($user->getId())));
    }
}
