<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Shop;

use App\Ecommerce\Application\DTO\User\UpdateUserRequestDto;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Infrastructure\Persistence\Entity\User\DoctrineUser;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The account of the signed-in customer. The account is always the one of the
 * token: the route carries no id, so there is no way to target someone else's.
 */
#[Route('/shop/me', name: 'shop_me_')]
class AccountController extends AbstractController
{
    #[Route('', name: 'get', methods: [Request::METHOD_GET])]
    public function get(#[CurrentUser] DoctrineUser $user, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($useCase->execute($user->getId())));
    }

    #[Route('', name: 'update', methods: [Request::METHOD_PUT])]
    public function update(
        #[CurrentUser] DoctrineUser $user,
        #[MapRequestPayload] UpdateUserRequestDto $dto,
        UpdateUserUseCase $useCase,
        UserPresenter $presenter,
    ): JsonResponse {
        return new JsonResponse($presenter->present($useCase->execute($user->getId(), $dto)));
    }

    #[Route('', name: 'delete', methods: [Request::METHOD_DELETE])]
    public function delete(#[CurrentUser] DoctrineUser $user, DeleteUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($user->getId());

        return new JsonResponse(['message' => 'User deleted successfully']);
    }
}
