<?php

declare(strict_types=1);

namespace App\Ecommerce\Infrastructure\Controller\Shop;

use App\Ecommerce\Application\DTO\User\UpdateUserRequestDto;
use App\Ecommerce\Application\UseCase\User\DeleteUserUseCase;
use App\Ecommerce\Application\UseCase\User\GetUserUseCase;
use App\Ecommerce\Application\UseCase\User\UpdateUserUseCase;
use App\Ecommerce\Infrastructure\Presenter\User\UserPresenter;
use App\Ecommerce\Infrastructure\Security\Voter\UserAccountVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The account of the signed-in customer. UserAccountVoter refuses any id but their own.
 */
#[Route('/api/shop/users/{id}', name: 'shop_account_', requirements: ['id' => Requirement::UUID])]
#[IsGranted(UserAccountVoter::ACCESS, subject: 'id', message: 'Access denied.')]
class AccountController extends AbstractController
{
    #[Route('', name: 'get', methods: [Request::METHOD_GET])]
    public function get(string $id, GetUserUseCase $useCase, UserPresenter $presenter): JsonResponse
    {
        return new JsonResponse($presenter->present($useCase->execute($id)));
    }

    #[Route('', name: 'update', methods: [Request::METHOD_PUT])]
    public function update(
        string $id,
        #[MapRequestPayload] UpdateUserRequestDto $dto,
        UpdateUserUseCase $useCase,
        UserPresenter $presenter,
    ): JsonResponse {
        return new JsonResponse($presenter->present($useCase->execute($id, $dto)));
    }

    #[Route('', name: 'delete', methods: [Request::METHOD_DELETE])]
    public function delete(string $id, DeleteUserUseCase $useCase): JsonResponse
    {
        $useCase->execute($id);

        return new JsonResponse(['message' => 'User deleted successfully']);
    }
}
